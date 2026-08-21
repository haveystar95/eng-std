<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * How many PAID search lookups one learner may trigger per day.
 *
 * Flat, not tiered, and that is deliberate. The collection quota
 * ({@see GenerationDailyLimit}) differentiates plans because generating a
 * collection is a product people pay for; a lookup is a dictionary entry costing a fraction of a
 * cent, and gating it by plan would turn the search field — the app's cheapest, most useful
 * surface — into a paywall pitch. This is a runaway guard, not a plan differentiator: 30 words a
 * day is far past what anyone types on purpose and nowhere near what a stuck retry loop costs.
 *
 * Cache hits do not count against it. Nothing was bought, so nothing is spent.
 */
final class SearchLookupDailyLimit
{
    public const DEFAULT_CAP = 30;

    public function __construct(private readonly int $cap = self::DEFAULT_CAP) {}

    public function cap(): int
    {
        return max(0, $this->cap);
    }

    public function allows(int $paidToday): bool
    {
        return $paidToday < $this->cap();
    }
}
