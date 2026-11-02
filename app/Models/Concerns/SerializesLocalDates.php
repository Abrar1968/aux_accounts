<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * JSON dates in Asia/Dhaka: business dates (midnight) as `Y-m-d`, timestamps as ISO-8601 with
 * offset. Eloquent's default (UTC ISO) would shift Dhaka dates to the previous day.
 */
trait SerializesLocalDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        $d = CarbonImmutable::instance($date)->setTimezone(config('app.timezone'));

        return $d->format('H:i:s') === '00:00:00' ? $d->toDateString() : $d->format('Y-m-d\TH:i:sP');
    }
}
