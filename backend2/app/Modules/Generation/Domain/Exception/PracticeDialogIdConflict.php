<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use RuntimeException;

/**
 * A client-supplied dialog id already belongs to a different user, or names a session that has
 * already ended. A fresh dialog needs a fresh id — reject rather than reuse or resurrect one.
 */
final class PracticeDialogIdConflict extends RuntimeException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('This practice dialog id is already in use.');
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'practice_dialog_id_conflict';
    }

    public function problemTitle(): string
    {
        return 'Practice dialog id already in use';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
