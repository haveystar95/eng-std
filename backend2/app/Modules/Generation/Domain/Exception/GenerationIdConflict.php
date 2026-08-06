<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use RuntimeException;

/**
 * A client-supplied generation id already belongs to a different user. Astronomically unlikely with
 * ULIDs, but we never reuse or leak another user's request — reject rather than resolve.
 */
final class GenerationIdConflict extends RuntimeException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('This generation id is already in use.');
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'generation_id_conflict';
    }

    public function problemTitle(): string
    {
        return 'Generation id already in use';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
