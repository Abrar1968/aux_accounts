<?php

namespace App\Services\Accounting\Engine;

use App\Support\Money;
use App\Support\Period;

/**
 * Revenue_Recognition!F:S port (one row). Inputs are plain values so this is unit-testable.
 */
class RecognitionCalculator
{
    /**
     * @param array{
     *   has_project:bool, period:?string, project_known:bool, method:string, project_type:string,
     *   contract_value:float, opening_progress:float, ending_progress:?float, override:mixed,
     *   duplicate_in_month:bool, historical:bool
     * } $in
     * @param array{start:string, months:int, share:float} $siam
     * @return array{entry_check:string, auto_revenue:float, recognized_amount:float, siam_eligible:float, siam_share:float, recognition_month:?string}
     */
    public function compute(array $in, array $siam): array
    {
        $check = $in['historical'] ? 'OK' : $this->check($in);
        $isOverTime = $in['method'] === 'Over Time';
        $auto = (! $in['historical'] && $check === 'OK' && $isOverTime && $in['ending_progress'] !== null)
            ? Money::round(($in['ending_progress'] - $in['opening_progress']) * $in['contract_value'])
            : 0.0;
        $override = is_numeric($in['override']) ? (float) $in['override'] : null;
        $recognized = $in['historical']
            ? ($override ?? $auto)
            : ($check !== 'OK' ? 0.0 : ($override ?? $auto));

        $month = $in['period'] ? Period::of($in['period']) : null;
        $eligible = 0.0;
        if ($month && $in['project_type'] === 'International Project') {
            $start = Period::of($siam['start']);
            $end = $start->addMonths((int) $siam['months']);
            if (! $month->lt($start) && $month->lt($end)) {
                $eligible = $recognized;
            }
        }

        return [
            'entry_check' => $check,
            'auto_revenue' => $auto,
            'recognized_amount' => Money::round($recognized),
            'siam_eligible' => Money::round($eligible),
            'siam_share' => Money::round($eligible * $siam['share']),
            'recognition_month' => $month?->date(),
        ];
    }

    private function check(array $in): string
    {
        $override = $in['override'];
        $hasOverride = $override !== null && $override !== '';

        return match (true) {
            ! $in['has_project'] => 'Project ID required',
            ! $in['period'] => 'Date required',
            ! $in['project_known'] => 'Unknown project',
            $in['duplicate_in_month'] => 'Duplicate project/month',
            $in['contract_value'] <= 0 => 'Contract value required',
            $hasOverride => is_numeric($override) ? 'OK' : 'Invalid override',
            $in['method'] !== 'Over Time' => 'Manual override required',
            $in['ending_progress'] === null => 'Ending progress required',
            $in['ending_progress'] < 0 || $in['ending_progress'] > 1 || $in['ending_progress'] < $in['opening_progress'] => 'Invalid progress',
            default => 'OK',
        };
    }
}
