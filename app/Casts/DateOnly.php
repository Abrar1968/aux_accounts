<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Business-date cast: always persisted as `Y-m-d` (MySQL DATE and SQLite text compare identically),
 * hydrated as an immutable Carbon at midnight Asia/Dhaka.
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse(substr((string) $value, 0, 10), config('app.timezone'))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(config('app.timezone'))->toDateString();
        }

        return CarbonImmutable::parse((string) $value, config('app.timezone'))->toDateString();
    }
}
