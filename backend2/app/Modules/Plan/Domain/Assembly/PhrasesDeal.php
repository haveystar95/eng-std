<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

/**
 * «ФРАЗЫ» AS DEALT, AND WHAT THE LADDER DID TO GET THERE (наряд BACK-TAILS-2 §1): the cards, what they cost by the day's
 * pace against the stage's ceiling, the stage after every rung, and what every frame with a window was left with — so the
 * build log, the report and the tests print the numbers the ladder actually cut by, not a second reckoning of them.
 */
final readonly class PhrasesDeal
{
    /** The stage as first built: every frame its recognitions up to two and every value round the level allows. */
    public const BUILT = 0;

    /** Rung 1: the third recognitions that fit. */
    public const THIRD_RECOGNITIONS = 1;

    /** Rung 2: the third value round cut off the least said frames. */
    public const THIRD_ROUNDS = 2;

    /** Rung 3: the second recognition cut off the least said frames. */
    public const SECOND_RECOGNITIONS = 3;

    /**
     * @param  list<CardDraft>  $drafts
     * @param  list<array{rung: int, seconds: int, cards: int}>  $rungs  the stage after each rung, in the ladder's order
     * @param  array<string, array{recognitions: int, rounds: int, own: bool}>  $frames  what every frame with a window kept, by ref
     */
    public function __construct(
        public array $drafts,
        public int $seconds,
        public int $budget,
        public array $rungs,
        public array $frames,
    ) {}

    /**
     * THE STOP SIGNAL (наряд BACK-TAILS-2 §1, п. 4): the ladder has spent every rung and the stage is still over its
     * ceiling. The day is dealt anyway — the excess is a warning in the day's build log, not a refusal and not a rollback.
     */
    public function overCeiling(): bool
    {
        return $this->seconds > $this->budget;
    }
}
