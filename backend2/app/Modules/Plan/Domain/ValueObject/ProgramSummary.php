<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE COUNTS A PROGRAMME TAB'S BROW IS WRITTEN FROM (DAY-UI-2): how many units the tab has, how
 * many are walked, and how many of them CAME BACK from earlier days (`returns`, наряд FIX-3 §9: «Вернулось» — the zal's
 * day 2 dialogue is 8 own lines and 7 returned). The client words them and counts nothing itself.
 */
final readonly class ProgramSummary
{
    public function __construct(
        public int $total,
        public int $done,
        public int $returns,
    ) {}

    /**
     * @param  list<UnitState>  $states  every unit of the tab
     * @param  int  $returned  how many of them came back from earlier days
     */
    public static function of(array $states, int $returned): self
    {
        return new self(
            count($states),
            count(array_filter($states, static fn (UnitState $s): bool => $s === UnitState::Done)),
            max(0, $returned),
        );
    }
}
