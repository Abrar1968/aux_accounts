<?php

namespace App\Services\Accounting\Engine;

use App\Models\FixedAsset;
use App\Support\Money;
use App\Support\Period;

/**
 * Asset_Schedule port. Straight-line, monthly, capped at depreciable base, with
 * opening accumulated depreciation carried from before the model start.
 */
class DepreciationCalculator
{
    /**
     * @param  list<Period>  $periods  consecutive months starting at model start
     * @return array<string, array{dep: float, nbv: float}> keyed by Y-m
     */
    public function schedule(FixedAsset $a, array $periods): array
    {
        $out = [];
        $accum = (float) $a->opening_accumulated_dep;
        $base = (float) $a->cost - (float) $a->residual_value;
        $monthly = $a->monthlyDepreciation();
        $acq = $a->acquisition_date?->toDateString();
        $svc = $a->in_service_date?->toDateString();
        $disp = $a->disposal_date?->toDateString();

        foreach ($periods as $p) {
            $cut = $p->cut();
            $dep = 0.0;
            $inactive = $acq === null || $svc === null || $acq >= $cut || $svc >= $cut
                || ($disp !== null && $disp < $p->date());
            if (! $inactive) {
                $dep = max(0.0, min($monthly, $base - $accum));
            }
            $accum += $dep;
            $nbv = ($acq === null || $acq >= $cut) ? 0.0 : max((float) $a->residual_value, (float) $a->cost - $accum);
            $out[$p->key()] = ['dep' => Money::round($dep), 'nbv' => Money::round($nbv)];
        }

        return $out;
    }
}
