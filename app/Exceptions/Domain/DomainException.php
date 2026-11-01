<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/** Base class for business-rule violations; rendered as JSON by bootstrap/app.php. */
class DomainException extends RuntimeException
{
    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'DOMAIN_RULE';
    }
}
