<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Collaborator;
use App\Models\CustomerPayment;
use App\Models\PartnershipAssignment;
use App\Models\PartnershipPackage;
use App\Models\PartnershipRevenue;
use App\Services\Shared\AuditService;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

/**
 * Partnerships module (AuxtechPartnerships.gs was never installed – implemented natively).
 * Active assignments (with an effective date, collaborator not Inactive) create one row per month,
 * bounded by the collaborator's agreement dates when those are set. Rows appear as soon as an
 * assignment or collaborator is saved, and nightly via accounting:daily.
 * Blank earned amount = not recognised; 0 = explicit zero. Method and share % are snapshotted.
 */
class PartnershipService
{
    public function __construct(
        private readonly PeriodService $periods,
        private readonly SequenceService $sequences,
    ) {}

    /**
     * Create the monthly rows of every Active assignment up to $through (idempotent) and remove
     * empty rows that fall outside an assignment's window (dates shortened, set Draft/Inactive).
     * A row is only removed while it has no earned input and no receipt.
     */
    public function generateThrough(Period $through): int
    {
        $created = 0;
        foreach (PartnershipAssignment::query()->with('collaborator')->get() as $a) {
            $created += $this->syncAssignment($a, $through);
        }

        return $created;
    }

    /** Rows for one assignment; returns how many were created. */
    public function syncAssignment(PartnershipAssignment $a, ?Period $through = null): int
    {
        $through ??= Period::current();
        $months = $this->window($a, $through);
        $keep = array_map(fn (Period $m) => $m->date(), $months);
        $created = 0;
        foreach ($months as $m) {
            $row = PartnershipRevenue::query()->firstOrCreate(
                ['assignment_id' => $a->id, 'earned_month' => $m->date()],
                ['code' => 'PART-'.$m->start->format('Ym').'-'.$a->code, 'earning_method' => $a->earning_method, 'share_percent' => $a->share_percent],
            );
            $created += $row->wasRecentlyCreated ? 1 : 0;
        }
        $paid = CustomerPayment::query()->where('receivable_type', 'partnership')->pluck('receivable_id')->all();
        PartnershipRevenue::query()->where('assignment_id', $a->id)
            ->whereNull('recognized_revenue')->whereNull('gross_sales')->whereNull('direct_amount')
            ->whereNotIn('id', $paid)->get()
            ->filter(fn (PartnershipRevenue $r) => ! in_array($r->earned_month->toDateString(), $keep, true))
            ->each(fn (PartnershipRevenue $r) => $r->delete());

        return $created;
    }

    /**
     * Months an assignment earns in: from the later of its effective date and the collaborator's
     * agreement start (when set) to the earliest of its through date, the agreement end and $through.
     *
     * @return list<Period>
     */
    public function window(PartnershipAssignment $a, Period $through): array
    {
        return $this->blockReason($a) !== null ? [] : $this->range($a, $through);
    }

    /** Why an assignment creates no monthly rows (null = it does). */
    public function blockReason(PartnershipAssignment $a): ?string
    {
        $c = $a->collaborator;

        return match (true) {
            $a->status !== 'Active' => "Assignment is {$a->status} — set it to Active",
            ! $a->effective_from => 'Assignment has no effective-from date',
            ! $c => 'Collaborator is missing',
            $c->status === 'Inactive' => 'Collaborator is Inactive',
            default => $this->range($a, Period::of($a->effective_from->toDateString())->addMonths(1200)) === []
                ? 'Outside the collaborator agreement dates' : null,
        };
    }

    /** @return list<Period> */
    private function range(PartnershipAssignment $a, Period $through): array
    {
        $c = $a->collaborator;
        $from = Period::of(max(array_filter([$a->effective_from->toDateString(), $c?->agreement_start?->toDateString()])));
        $ends = array_filter([$a->effective_through?->toDateString(), $c?->agreement_end?->toDateString()]);
        $to = $ends ? Period::of(min($ends)) : $through;
        if ($through->lt($to)) {
            $to = $through;
        }

        return $from->lte($to) ? Period::range($from, $to) : [];
    }

    public function overview(): array
    {
        $this->generateThrough(Period::current()); // self-healing: rows exist the moment the page is opened

        return [
            'collaborators' => Collaborator::query()->orderBy('code')->get(),
            'packages' => PartnershipPackage::query()->orderBy('code')->get(),
            'assignments' => PartnershipAssignment::query()->with('collaborator', 'package:id,code,name')->withCount('revenues')->orderBy('code')->get()
                ->map(fn (PartnershipAssignment $a) => $a->toArray() + ['months' => $a->revenues_count, 'no_rows_reason' => $this->blockReason($a)]),
        ];
    }

    public function createCollaborator(array $data): Collaborator
    {
        $n = $this->sequences->next('COL', Collaborator::query()->count());

        return Collaborator::query()->create($data + ['code' => 'COL-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT)]);
    }

    public function createAssignment(array $data): PartnershipAssignment
    {
        $n = $this->sequences->next('ASM', PartnershipAssignment::query()->count());

        $a = PartnershipAssignment::query()->create($data + ['code' => 'ASM-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT)]);
        $this->syncAssignment($a->load('collaborator'));

        return $a;
    }

    public function updateCollaborator(Collaborator $c, array $data): Collaborator
    {
        $c->update($data);
        PartnershipAssignment::query()->with('collaborator')->where('collaborator_id', $c->id)->get()->each(fn ($a) => $this->syncAssignment($a));

        return $c;
    }

    public function deleteCollaborator(Collaborator $c): void
    {
        if (PartnershipAssignment::query()->where('collaborator_id', $c->id)->exists()) {
            throw new DomainException("{$c->name} has package assignments. Delete those or mark the collaborator inactive.");
        }
        $c->delete();
    }

    public function updateAssignment(PartnershipAssignment $a, array $data): PartnershipAssignment
    {
        $a->update($data);
        $this->syncAssignment($a->load('collaborator'));

        return $a;
    }

    /** Only while no revenue has been earned or paid; empty generated month rows are removed with it. */
    public function deleteAssignment(PartnershipAssignment $a, string $reason): void
    {
        $revenues = PartnershipRevenue::query()->where('assignment_id', $a->id)->get();
        $ids = $revenues->pluck('id');
        if ($revenues->contains(fn ($r) => (float) $r->recognized_revenue != 0.0)
            || CustomerPayment::query()->where('receivable_type', 'partnership')->whereIn('receivable_id', $ids)->exists()) {
            throw new DomainException("{$a->code} has earned revenue or receipts. Set it to Inactive instead.");
        }
        DB::transaction(function () use ($a, $ids, $reason) {
            PartnershipRevenue::query()->whereIn('id', $ids)->delete();
            app(AuditService::class)->withReason($reason, fn () => $a->delete());
        });
    }

    public function setEarning(PartnershipRevenue $r, ?float $grossSales, ?float $directAmount, ?string $notes = null): PartnershipRevenue
    {
        $this->periods->assertOpen($r->earned_month);
        if (($grossSales !== null && $grossSales < 0) || ($directAmount !== null && $directAmount < 0)) {
            throw new DomainException('Earned amounts cannot be negative.');
        }
        $recognized = $r->earning_method === 'Share of Sales'
            ? ($grossSales !== null && $r->share_percent !== null ? Money::round($grossSales * (float) $r->share_percent) : null)
            : ($directAmount !== null ? Money::round($directAmount) : null);
        $r->update(['gross_sales' => $grossSales, 'direct_amount' => $directAmount, 'recognized_revenue' => $recognized, 'notes' => $notes ?? $r->notes]);

        return $r;
    }
}
