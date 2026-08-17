<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/** No such dialog for this user — a 404 (never existence disclosure to a non-owner). */
final class PracticeDialogNotFound extends DomainException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('Practice dialog not found.');
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'practice_dialog_not_found';
    }

    public function problemTitle(): string
    {
        return 'Practice dialog not found';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
