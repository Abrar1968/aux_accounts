<?php

namespace App\Exceptions\Domain;

class PostingRejectedException extends DomainException
{
    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'POSTING_REJECTED';
    }
}
