<?php

namespace Database\Seeders;

use App\Services\Shared\SettingsService;
use Illuminate\Database\Seeder;

/** SettingsService::DEFAULTS overlaid with the workbook Settings tab values. */
class SettingsSeeder extends Seeder
{
    use LoadsWorkbookJson;

    private const MAP = [
        'Accounting Basis' => 'accounting.basis',
        'Model Start Date' => 'accounting.model_start',
        'Opening Balance Date' => 'accounting.opening_date',
        'AC Useful Life' => 'assets.ac_useful_life',
        'AC Residual Value' => 'assets.ac_residual',
        'Siam Contract Start' => 'siam.start',
        'Siam Contract Months' => 'siam.months',
        'Siam International Revenue Share' => 'siam.share',
        'Referral Recognition Policy' => 'referral.policy',
    ];

    private const OPENING = [
        'Other pre-Apr fixed assets / equipment' => 'opening.other_fixed_assets',
        'Other opening assets / deposits' => 'opening.other_assets',
        'Cash before Apr-1 capital contribution' => 'opening.cash',
        'Other opening liabilities' => 'opening.other_liabilities',
    ];

    public function run(): void
    {
        $settings = app(SettingsService::class);
        $values = SettingsService::DEFAULTS;
        foreach ($this->json('accounting/settings') as $row) {
            if (isset(self::MAP[$row['Setting']])) {
                $values[self::MAP[$row['Setting']]] = $row['Value'];
            }
        }
        foreach ($this->json('accounting/opening_balance') as $row) {
            if (isset(self::OPENING[$row['Known Assets']])) {
                $values[self::OPENING[$row['Known Assets']]] = (float) ($row['col2'] ?? 0);
            }
        }
        foreach ($values as $key => $value) {
            $settings->set($key, $value, explode('.', $key)[0]);
        }
        $settings->flush();
    }
}
