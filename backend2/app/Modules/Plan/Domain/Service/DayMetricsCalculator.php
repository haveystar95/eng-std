<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;

/**
 * The numbers of a day, from its cards (`docs/plan-v2.md` §6): cards dealt, cards done and minutes
 * spent (answer timestamps, pauses longer than {@see PAUSE_SECONDS} not counted).
 *
 * Since наряд CONV-1 a day can also spend minutes on something that answers no card — the talk with
 * the agent. Those minutes are handed in rather than found here: the talk keeps its own start and
 * end, and a calculator that went looking for them would be reading a second journal.
 */
final class DayMetricsCalculator
{
    public const PAUSE_SECONDS = 600;

    /**
     * @param  list<DayCard>  $cards
     * @param  int  $extraMinutes  minutes spent on what answers no card — the talk of the sixth stage
     */
    public function calculate(array $cards, int $extraMinutes = 0): DayMetrics
    {
        $done = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->isAnswered()));

        return new DayMetrics(
            cardsTotal: count($cards),
            cardsDone: count($done),
            minutesSpent: $this->minutes($done) + max(0, $extraMinutes),
        );
    }

    /** @param list<DayCard> $done */
    private function minutes(array $done): int
    {
        $times = [];
        foreach ($done as $card) {
            $at = $card->answeredAt();
            if ($at !== null) {
                $times[] = $at->getTimestamp();
            }
        }
        if (count($times) < 2) {
            return $times === [] ? 0 : 1;
        }
        sort($times);
        $seconds = 0;
        for ($i = 1; $i < count($times); $i++) {
            $gap = $times[$i] - $times[$i - 1];
            if ($gap <= self::PAUSE_SECONDS) {
                $seconds += $gap;
            }
        }

        return max(1, (int) ceil($seconds / 60));
    }
}
