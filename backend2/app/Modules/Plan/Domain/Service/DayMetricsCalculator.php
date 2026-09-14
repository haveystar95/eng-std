<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;

/**
 * The numbers of a day, from its cards (`docs/plan-v2.md` §6): cards dealt, cards done and minutes
 * spent (answer timestamps, pauses longer than {@see PAUSE_SECONDS} not counted).
 */
final class DayMetricsCalculator
{
    public const PAUSE_SECONDS = 600;

    /** @param  list<DayCard>  $cards */
    public function calculate(array $cards): DayMetrics
    {
        $done = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->isAnswered()));

        return new DayMetrics(
            cardsTotal: count($cards),
            cardsDone: count($done),
            minutesSpent: $this->minutes($done),
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
