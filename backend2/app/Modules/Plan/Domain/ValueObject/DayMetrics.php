<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** What a closed day is summarised by (`docs/plan-v2.md` §6). */
final readonly class DayMetrics
{
    public function __construct(
        public int $cardsTotal,
        public int $cardsDone,
        public int $minutesSpent,
        /** Share of graded cards passed without a hint on the first attempt, 0..1; null when nothing was graded. */
        public ?float $firstTryShare,
        public ?UnitKind $hardestUnitKind,
        public ?string $hardestUnitRef,
        public ?string $hardestUnitText,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0, null, null, null, null);
    }
}
