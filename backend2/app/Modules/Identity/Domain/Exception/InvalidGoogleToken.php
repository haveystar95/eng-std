<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use DomainException;

final class InvalidGoogleToken extends DomainException
{
    public static function make(): self
    {
        return new self('The Google ID token could not be verified.');
    }
}
