<?php

namespace App\Exceptions\Domain;

class IdempotencyConflictException extends DomainException
{
    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'IDEMPOTENCY_CONFLICT';
    }
}
