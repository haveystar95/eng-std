<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** What a day is counted by (`docs/plan-v2.md` §6): cards dealt, cards answered, minutes spent. */
final readonly class DayMetrics
{
    public function __construct(
        public int $cardsTotal,
        public int $cardsDone,
        public int $minutesSpent,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0);
    }
}
