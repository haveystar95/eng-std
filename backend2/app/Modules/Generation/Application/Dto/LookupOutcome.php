<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What came of a lookup: an answer, or an honest «not today».
 *
 * The cap is NOT an error, and this DTO is why. «На сегодня лимит» is a normal state the app has a
 * screen for — it shows the database results it already has and says so — and modelling it as a
 * thrown exception would make the honest answer the exceptional path and tempt every caller into
 * catching it.
 */
final readonly class LookupOutcome
{
    private function __construct(
        public ?CachedLookup $lookup,
        public bool $capReached,
        public int $dailyCap,
        public int $usedToday,
        /**
         * The model could not name a word for this query. Also not an error, and for the same
         * reason: «проверьте написание» is advice, and advice does not belong on an error path.
         */
        public bool $notRecognized = false,
    ) {}

    public static function answered(CachedLookup $lookup, int $dailyCap, int $usedToday): self
    {
        return $lookup->notRecognized
            ? self::notRecognized($dailyCap, $usedToday)
            : new self($lookup, false, $dailyCap, $usedToday);
    }

    public static function capReached(int $dailyCap, int $usedToday): self
    {
        return new self(null, true, $dailyCap, $usedToday);
    }

    public static function notRecognized(int $dailyCap, int $usedToday): self
    {
        return new self(null, false, $dailyCap, $usedToday, notRecognized: true);
    }
}
