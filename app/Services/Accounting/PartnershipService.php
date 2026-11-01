<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\PartnershipAssignment;
use App\Models\PartnershipRevenue;
use App\Services\Shared\PeriodService;
use App\Support\Money;
use App\Support\Period;

/**
 * Partnerships module (AuxtechPartnerships.gs was never installed – implemented natively).
 * Only Active assignments of Active collaborators with a real effective date create rows.
 * Blank earned amount = not recognised; 0 = explicit zero. Method and share % are snapshotted.
 */
class PartnershipService
{
    public function __construct(private readonly PeriodService $periods)
    {
    }

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
