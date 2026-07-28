<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use RuntimeException;

/** The model's draft failed validation (too few items, empty fields, all duplicates, …). */
final class InvalidGeneratedDraft extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("Generated draft rejected: {$reason}");
    }
}
