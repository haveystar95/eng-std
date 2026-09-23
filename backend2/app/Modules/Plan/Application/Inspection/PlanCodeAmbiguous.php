<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use RuntimeException;

/** Two plans share a code (characters 5–10 of their ULIDs) — the page asks for the full id instead of guessing. */
final class PlanCodeAmbiguous extends RuntimeException
{
    /** @param list<string> $ids */
    private function __construct(string $message, public readonly array $ids)
    {
        parent::__construct($message);
    }

    /** @param list<string> $ids */
    public static function of(string $code, array $ids): self
    {
        return new self("Plan code {$code} names ".count($ids).' plans: '.implode(', ', $ids), $ids);
    }
}
