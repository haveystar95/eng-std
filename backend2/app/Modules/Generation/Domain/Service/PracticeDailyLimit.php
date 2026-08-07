<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * How many practice dialogs a premium user may start per UTC day. A flat number (not tiered):
 * only premium reaches the feature at all, and the cap is a cost guard, not a plan differentiator.
 * The value comes from config at the composition root; this service is the one place the handler
 * and any future quota view read it from, so they can never disagree.
 */
final class PracticeDailyLimit
{
    public const DEFAULT_PER_DAY = 5;

    public function __construct(private readonly int $perDay = self::DEFAULT_PER_DAY) {}

    public function perDay(): int
    {
        return $this->perDay;
    }
}
