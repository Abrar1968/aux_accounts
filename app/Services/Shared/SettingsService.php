<?php

namespace App\Services\Shared;

use App\Models\Setting;
use Illuminate\Support\Arr;

/**
 * Typed access to the policy table (workbook Settings tab + CRM System Config).
 */
class SettingsService
{
    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public const DEFAULTS = [
        'accounting.model_start' => '2026-04-01',
        'accounting.opening_date' => '2026-03-31',
        'accounting.basis' => 'Accrual',
        'accounting.currency' => 'BDT',
        'assets.ac_useful_life' => 60,
        'assets.ac_residual' => 0,
        'siam.start' => '2026-11-01',
        'siam.months' => 24,
        'siam.share' => 0.15,
        'referral.policy' => 'When Paid',
        'referral.tiers' => [
            ['below' => 30000, 'fixed' => 500],
            ['below' => 60000, 'fixed' => 600],
            ['below' => 100000, 'fixed' => 1000],
            ['below' => 250000, 'rate' => 0.015],
            ['below' => 500000, 'rate' => 0.025],
            ['below' => 750000, 'rate' => 0.05],
            ['below' => 1000000, 'rate' => 0.075],
            ['below' => null, 'rate' => 0.10],
        ],
        'opening.other_fixed_assets' => 0,
        'opening.other_assets' => 0,
        'opening.cash' => 0,
        'opening.other_liabilities' => 0,
        'crm.start' => '2026-10-07',
        'crm.daily_slots' => 30,
        'crm.default_owner' => 'Abrar',
        'crm.default_business_type' => 'E-commerce / Online Store',
        'crm.report_brand' => 'Sales & Marketing',
        'crm.phone_country_code' => '88',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        return Arr::get($this->cache, $key, $default ?? (self::DEFAULTS[$key] ?? null));
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value], 'group' => $group]);
        $this->cache = null;
    }

    public function all(): array
    {
        $this->load();

        return array_merge(self::DEFAULTS, $this->cache);
    }

    /** Policies an owner may change from the Settings page (others are model constants). */
    public const EDITABLE = [
        'assets.ac_useful_life', 'assets.ac_residual', 'siam.start', 'siam.months', 'siam.share', 'referral.tiers',
        'opening.other_fixed_assets', 'opening.other_assets', 'opening.cash', 'opening.other_liabilities',
        'crm.daily_slots', 'crm.default_owner', 'crm.default_business_type', 'crm.report_brand',
    ];

    /** @param array<string,mixed> $values */
    public function updateMany(array $values): array
    {
        $changed = [];
        foreach ($values as $key => $value) {
            if (! in_array($key, self::EDITABLE, true)) {
                throw new \App\Exceptions\Domain\DomainException("Setting {$key} is not editable.");
            }
            $this->set($key, $value, explode('.', $key)[0]);
            $changed[] = $key;
        }

        return $changed;
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) {
            return;
        }
        $this->cache = [];
        try {
            foreach (Setting::query()->get(['key', 'value']) as $s) {
                $this->cache[$s->key] = is_array($s->value) && array_key_exists('v', $s->value) ? $s->value['v'] : $s->value;
            }
        } catch (\Throwable) {
            // table not migrated yet – defaults apply
        }
    }
}
