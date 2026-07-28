<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

final class InvalidGoogleToken extends DomainException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('The Google ID token could not be verified.');
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'invalid_google_token';
    }

    public function problemTitle(): string
    {
        return 'Invalid Google token';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
