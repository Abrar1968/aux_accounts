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
 * Only Active assignments of Active collaborators with a real effective date create rows.
 * Blank earned amount = not recognised; 0 = explicit zero. Method and share % are snapshotted.
 */
class PartnershipService
{
    public function __construct(
        private readonly PeriodService $periods,
        private readonly SequenceService $sequences,
    ) {}

    public function generateThrough(Period $through): int
    {
        $created = 0;
        $assignments = PartnershipAssignment::query()->with('collaborator', 'package')
            ->where('status', 'Active')->whereNotNull('effective_from')->get();
        foreach ($assignments as $a) {
            if ($a->collaborator?->status !== 'Active' || ! $a->collaborator->agreement_start) {
                continue;
            }
            $from = Period::of(max($a->effective_from->toDateString(), $a->collaborator->agreement_start->toDateString()));
            $endDates = array_filter([$a->effective_through?->toDateString(), $a->collaborator->agreement_end?->toDateString()]);
            $to = $endDates ? Period::of(min($endDates)) : $through;
            if ($through->lt($to)) {
                $to = $through;
            }
            foreach ($from->lte($to) ? Period::range($from, $to) : [] as $m) {
                $row = PartnershipRevenue::query()->firstOrCreate(
                    ['assignment_id' => $a->id, 'earned_month' => $m->date()],
                    ['code' => 'PART-'.$m->start->format('Ym').'-'.$a->code, 'earning_method' => $a->earning_method, 'share_percent' => $a->share_percent],
                );
                $created += $row->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    public function overview(): array
    {
        return [
            'collaborators' => Collaborator::query()->orderBy('code')->get(),
            'packages' => PartnershipPackage::query()->orderBy('code')->get(),
            'assignments' => PartnershipAssignment::query()->with('collaborator:id,code,name', 'package:id,code,name')->orderBy('code')->get(),
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

        return PartnershipAssignment::query()->create($data + ['code' => 'ASM-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT)]);
    }

    public function updateCollaborator(Collaborator $c, array $data): Collaborator
    {
        $c->update($data);

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
            app(AuditService::class)->record('deleted', $a, $a->toArray(), null, $reason);
            $a->delete();
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
