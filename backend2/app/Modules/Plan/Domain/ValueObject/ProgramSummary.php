<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE COUNTS A PROGRAMME TAB'S BROW IS WRITTEN FROM (DAY-UI-2): how many units the tab has, how
 * many are walked, how many come back tomorrow — «СЛОВА · 8 · 6 ПРОЙДЕНО · 2 ВЕРНУТСЯ ЗАВТРА».
 * The client words them and counts nothing itself.
 */
final readonly class ProgramSummary
{
    public function __construct(
        public int $total,
        public int $done,
        public int $returns,
    ) {}

    /** @param list<UnitState> $states */
    public static function of(array $states): self
    {
        return new self(
            count($states),
            count(array_filter($states, static fn (UnitState $s): bool => $s === UnitState::Done)),
            count(array_filter($states, static fn (UnitState $s): bool => $s === UnitState::ReturnsTomorrow)),
        );
    }
}
