<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * How many characters a month the instant translator may spend, and when to stop.
 *
 * The free plan bills in CHARACTERS SENT, half a million a month, and the meter resets on the 1st.
 * What makes this worth a class rather than a constant is the SAFETY MARGIN: the app stops at 95%,
 * not at 100%.
 *
 * The 5% is not caution for its own sake. Running the meter to zero means the last few translations
 * of the month fail at the VENDOR instead of at us — and a vendor rejection arrives as a 456 in the
 * middle of somebody's search, is indistinguishable from an outage, and leaves the ledger and the
 * real quota disagreeing about who spent what. Stopping early makes the end of the month a decision
 * this app made and can explain, instead of an error it discovers.
 *
 * The count itself is exact rather than estimated: every character ever sent has a cache row with
 * its length on it ({@see \App\Modules\Generation\Application\Port\InstantTranslationCache}), so the
 * month's total is a SUM over rows and not a running counter that a crash could lose.
 */
final class TranslationMonthlyBudget
{
    /** DeepL's free tier. A paid plan would raise this, not remove it. */
    public const FREE_PLAN_CHARACTERS = 500_000;

    /** Stop here, not at the ceiling — see the class docblock. */
    public const SAFETY_RATIO = 0.95;

    public function __construct(private readonly int $monthlyCharacters = self::FREE_PLAN_CHARACTERS) {}

    /** The characters this app will actually spend before it stops. */
    public function usableCharacters(): int
    {
        return (int) floor(max(0, $this->monthlyCharacters) * self::SAFETY_RATIO);
    }

    /** May one more translation of `$length` characters be bought this month? */
    public function allows(int $usedThisMonth, int $length): bool
    {
        return $length > 0 && $usedThisMonth + $length <= $this->usableCharacters();
    }

    /** Has the month's allowance run out — the state the endpoint reports as `limit_reached`? */
    public function isExhausted(int $usedThisMonth): bool
    {
        return $usedThisMonth >= $this->usableCharacters();
    }
}
