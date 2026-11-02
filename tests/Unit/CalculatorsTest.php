<?php

namespace Tests\Unit;

use App\Models\FixedAsset;
use App\Services\Accounting\Engine\DepreciationCalculator;
use App\Services\Accounting\Engine\RecognitionCalculator;
use App\Services\Accounting\Engine\ReferralTierCalculator;
use App\Services\Shared\SettingsService;
use App\Support\Period;
use Tests\TestCase;

class CalculatorsTest extends TestCase
{
    private const SIAM = ['start' => '2026-11-01', 'months' => 24, 'share' => 0.15];

    public function test_referral_tiers_and_boundaries(): void
    {
        $c = new ReferralTierCalculator(SettingsService::DEFAULTS['referral.tiers']);
        $cases = [29999 => 500, 30000 => 600, 59999 => 600, 60000 => 1000, 99999 => 1000, 100000 => 1500,
            249999 => 3749.99, 250000 => 6250, 500000 => 25000, 750000 => 56250, 999999 => 74999.93, 1000000 => 100000];
        foreach ($cases as $value => $memo) {
            $this->assertEqualsWithDelta($memo, $c->memo($value), 0.001, "memo for $value");
        }
    }

    public function test_depreciation_matches_ac_schedule_and_caps(): void
    {
        $ac = new FixedAsset(['cost' => 132000, 'residual_value' => 0, 'useful_life_months' => 60, 'opening_accumulated_dep' => 19800,
            'acquisition_date' => '2025-07-01', 'in_service_date' => '2025-07-01']);
        $s = (new DepreciationCalculator)->schedule($ac, Period::range(Period::of('2026-04'), Period::of('2026-11')));
        $this->assertSame(2200.0, $s['2026-04']['dep']);
        $this->assertSame(110000.0, $s['2026-04']['nbv']);
        $this->assertSame(94600.0, $s['2026-11']['nbv']);

        $small = new FixedAsset(['cost' => 1000, 'residual_value' => 100, 'useful_life_months' => 2, 'opening_accumulated_dep' => 0,
            'acquisition_date' => '2026-04-10', 'in_service_date' => '2026-05-01']);
        $s = (new DepreciationCalculator)->schedule($small, Period::range(Period::of('2026-04'), Period::of('2026-07')));
        $this->assertSame([0.0, 450.0, 450.0, 0.0], array_column($s, 'dep'), 'not in service in April, capped at base');
        $this->assertSame(100.0, $s['2026-07']['nbv'], 'never below residual');
    }

    public function test_disposed_asset_stops_depreciating(): void
    {
        $a = new FixedAsset(['cost' => 1200, 'residual_value' => 0, 'useful_life_months' => 12, 'opening_accumulated_dep' => 0,
            'acquisition_date' => '2026-04-01', 'in_service_date' => '2026-04-01', 'disposal_date' => '2026-05-15']);
        $s = (new DepreciationCalculator)->schedule($a, Period::range(Period::of('2026-04'), Period::of('2026-07')));
        $this->assertSame([100.0, 100.0, 0.0, 0.0], array_column($s, 'dep'));
    }

    private function rec(array $over = []): array
    {
        return (new RecognitionCalculator)->compute(array_merge([
            'has_project' => true, 'period' => '2026-12-01', 'project_known' => true, 'method' => 'Over Time',
            'project_type' => 'Project', 'contract_value' => 100000.0, 'opening_progress' => 0.2, 'ending_progress' => 0.5,
            'override' => null, 'duplicate_in_month' => false, 'historical' => false,
        ], $over), self::SIAM);
    }

    public function test_recognition_entry_checks(): void
    {
        $this->assertSame(30000.0, $this->rec()['recognized_amount']);
        $this->assertSame('Project ID required', $this->rec(['has_project' => false])['entry_check']);
        $this->assertSame('Date required', $this->rec(['period' => null])['entry_check']);
        $this->assertSame('Unknown project', $this->rec(['project_known' => false])['entry_check']);
        $this->assertSame('Duplicate project/month', $this->rec(['duplicate_in_month' => true])['entry_check']);
        $this->assertSame('Contract value required', $this->rec(['contract_value' => 0.0])['entry_check']);
        $this->assertSame('Invalid override', $this->rec(['override' => 'abc'])['entry_check']);
        $this->assertSame('Manual override required', $this->rec(['method' => 'Point in Time'])['entry_check']);
        $this->assertSame('Ending progress required', $this->rec(['ending_progress' => null])['entry_check']);
        $this->assertSame('Invalid progress', $this->rec(['ending_progress' => 0.1])['entry_check']);
        $this->assertSame('Invalid progress', $this->rec(['ending_progress' => 1.2])['entry_check']);
        $this->assertSame(0.0, $this->rec(['ending_progress' => 0.1])['recognized_amount']);
        $this->assertSame(5000.0, $this->rec(['method' => 'Point in Time', 'override' => 5000])['recognized_amount']);
    }

    public function test_siam_window_applies_to_international_only(): void
    {
        $this->assertSame(4500.0, $this->rec(['project_type' => 'International Project'])['siam_share']);
        $this->assertSame(0.0, $this->rec()['siam_share']);
        $this->assertSame(0.0, $this->rec(['project_type' => 'International Project', 'period' => '2026-10-31'])['siam_share'], 'before start');
        $this->assertSame(0.0, $this->rec(['project_type' => 'International Project', 'period' => '2028-11-01'])['siam_share'], 'after 24 months');
        $this->assertSame(4500.0, $this->rec(['project_type' => 'International Project', 'period' => '2028-10-15'])['siam_share'], 'last month');
    }
}
