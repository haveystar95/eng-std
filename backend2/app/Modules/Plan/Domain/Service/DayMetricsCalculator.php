<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;

/**
 * The numbers of a day, from its cards (`docs/plan-v2.md` §6): cards done, minutes spent (answer
 * timestamps, pauses longer than {@see PAUSE_SECONDS} not counted), «верно с первого раза» over
 * graded cards, and the unit that took the most attempts.
 */
final class DayMetricsCalculator
{
    public const PAUSE_SECONDS = 600;

    /**
     * @param  list<DayCard>  $cards
     * @param  callable(DayCard): ?string  $unitText  how a unit is named on the screen
     */
    public function calculate(array $cards, callable $unitText): DayMetrics
    {
        $done = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->isAnswered()));

        $graded = 0;
        $firstTry = 0;
        $attemptsByUnit = [];
        $cardByUnit = [];
        foreach ($done as $card) {
            if ($card->isGraded()) {
                $graded++;
                if ($card->result() === CardResult::Passed && $card->attempts() <= 1) {
                    $firstTry++;
                }
            }
            $key = $card->unitKind()->value.':'.$card->unitRef();
            $attemptsByUnit[$key] = ($attemptsByUnit[$key] ?? 0) + $card->attempts();
            $cardByUnit[$key] ??= $card;
        }

        $hardest = null;
        $max = 0;
        foreach ($attemptsByUnit as $key => $attempts) {
            if ($attempts > $max) {
                $max = $attempts;
                $hardest = $cardByUnit[$key];
            }
        }

        return new DayMetrics(
            cardsTotal: count($cards),
            cardsDone: count($done),
            minutesSpent: $this->minutes($done),
            firstTryShare: $graded > 0 ? round($firstTry / $graded, 2) : null,
            hardestUnitKind: $hardest?->unitKind(),
            hardestUnitRef: $hardest?->unitRef(),
            hardestUnitText: $hardest !== null ? $unitText($hardest) : null,
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
