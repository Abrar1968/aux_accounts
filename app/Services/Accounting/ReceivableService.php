<?php

namespace App\Services\Accounting;

use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;
use App\Repositories\Contracts\MarketingServiceRepositoryInterface;
use App\Repositories\Contracts\OpeningReceivableRepositoryInterface;
use App\Repositories\Contracts\PartnershipRevenueRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\RevenueRecognitionRepositoryInterface;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Receivables tab port. A receivable is any record a customer can owe against
 * (project, marketing service, opening A/R, partnership revenue). Balance at a cut-off:
 * opening + earned (before cut) − gross settlements (cash + withheld fee, paid before cut).
 */
class ReceivableService
{
    public const TYPES = ['project', 'marketing', 'opening', 'partnership'];

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly RevenueRecognitionRepositoryInterface $recognitions,
        private readonly MarketingServiceRepositoryInterface $marketing,
        private readonly OpeningReceivableRepositoryInterface $opening,
        private readonly PartnershipRevenueRepositoryInterface $partnerships,
        private readonly CustomerPaymentRepositoryInterface $payments,
    ) {
    }

    /** @return Collection<int, array<string,mixed>> */
    public function balancesAt(?string $cut = null): Collection
    {
        $before = fn ($q, string $col) => $cut ? $q->whereNotNull($col)->where($col, '<', $cut) : $q;

        $rr = $before($this->recognitions->query(), 'recognition_month')
            ->selectRaw('project_id, SUM(recognized_amount) s')->groupBy('project_id')->pluck('s', 'project_id');
        $settled = $before($this->payments->query(), 'paid_at')
            ->selectRaw('receivable_type, receivable_id, SUM(cash_received + processing_fee) s')
            ->groupBy('receivable_type', 'receivable_id')->get()
            ->mapWithKeys(fn ($r) => [$r->receivable_type.':'.$r->receivable_id => (float) $r->s]);

        $rows = collect();
        $push = function (string $type, $id, string $code, string $name, string $kind, float $opening, float $earned) use (&$rows, $settled) {
            $paid = $settled[$type.':'.$id] ?? 0.0;
            $bal = Money::round($opening + $earned - $paid);
            $rows->push([
                'type' => $type, 'id' => $id, 'code' => $code, 'name' => $name, 'kind' => $kind,
                'opening' => Money::round($opening), 'earned' => Money::round($earned), 'settled' => Money::round($paid),
                'balance' => $bal, 'ar' => max(0.0, $bal), 'advance' => max(0.0, -$bal),
            ]);
        };

        foreach ($this->opening->query()->orderBy('id')->get() as $o) {
            $push('opening', $o->id, $o->code, $o->customer, 'Legacy A/R', (float) $o->opening_amount, 0.0);
        }
        foreach ($this->projects->query()->orderBy('id')->get() as $p) {
            $push('project', $p->id, $p->code, $p->name, $p->typeLabel(), 0.0, (float) ($rr[$p->id] ?? 0));
        }
        foreach ($this->marketing->query()->orderBy('id')->get() as $m) {
            $earned = ($cut === null || $m->service_month->toDateString() < $cut) ? (float) $m->revenue : 0.0;
            $push('marketing', $m->id, $m->code, $m->client, 'Marketing Service', 0.0, $earned);
        }
        foreach ($this->partnerships->query()->with('assignment.collaborator')->orderBy('id')->get() as $r) {
            $earned = ($cut === null || $r->earned_month->toDateString() < $cut) ? (float) ($r->recognized_revenue ?? 0) : 0.0;
            $push('partnership', $r->id, $r->code, $r->assignment?->collaborator?->name ?? $r->code, 'Partnership', 0.0, $earned);
        }

        return $rows;
    }

    /** @return array{ar: float, advance: float} */
    public function totalsAt(string $cut): array
    {
        $rows = $this->balancesAt($cut);

        return ['ar' => Money::sum($rows->pluck('ar')), 'advance' => Money::sum($rows->pluck('advance'))];
    }

    /** Aging of current open A/R by days since the earned date (new report). */
    public function aging(string $asOf): Collection
    {
        return $this->balancesAt(null)->where('ar', '>', 0)->values();
    }

    /** Searchable picker for the payment form (replaces Payments!E XLOOKUP on names). */
    public function options(?string $q = null): Collection
    {
        return $this->balancesAt(null)
            ->filter(fn ($r) => $q === null || stripos($r['code'].' '.$r['name'], $q) !== false)
            ->values();
    }
}
