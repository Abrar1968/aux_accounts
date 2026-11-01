<?php

namespace App\Exceptions\Domain;

class PeriodLockedException extends DomainException
{
    public function status(): int
    {
        return 423;
    }

    public function errorCode(): string
    {
        return 'PERIOD_LOCKED';
    }
}
