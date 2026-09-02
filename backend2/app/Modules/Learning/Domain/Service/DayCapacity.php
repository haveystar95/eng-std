<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * HOW MANY CARDS FIT IN A DAY — the one table, in one place.
 *
 * Three questions read it and they must not be allowed to answer differently:
 *
 *   the SCHEDULER   how many days a plan needs — {@see PlanScheduler}
 *   the PREVIEW     what the learner is shown before they commit (the same `compute()`)
 *   the DAY BRIEF   how many cards P2 is asked for, and how they split
 *
 * Until v0.2 the numbers lived in three places at once: a const in the scheduler, a literal in the
 * offline double, and a table written out in the prompt. The prompt and the scheduler drifted by
 * design («the prompt states a band and this states the number at the bottom of it»), which meant a
 * day could come back at the top of its band and the scheduler would call the plan «не влезает» —
 * a disagreement between two halves of the same rule, wearing the costume of a fact about the
 * learner's plan. P1 is no longer told anything about days, so there is exactly one copy left, and
 * it is this one.
 *
 * ## The numbers, and why they moved
 *
 * v0.1: 10 → 5, 20 → 9, 40 → 16. v0.2: **10 → 7, 20 → 14, 40 → 24.**
 *
 * The old figures were a guess at «how many cards can a person learn in twenty minutes» and they
 * were measured against days made of long, memorised replies. A v0.2 day is made of FRAMES with a
 * slot and the words that go in it — eight lines and six words are twenty sentences, not fourteen
 * things to memorise — so the same twenty minutes carries more cards, and a day of nine was
 * leaving the learner with a conversation they could not hold.
 */
final class DayCapacity
{
    /**
     * Terms per day, by minutes per day. Three measured points and a straight line between them.
     *
     * @var array<int, int>
     */
    private const ANCHORS = [10 => 7, 20 => 14, 40 => 24];

    /**
     * How many terms one day holds at this many minutes.
     *
     * Piecewise-linear through the three anchors, extended at both ends with the slope of the
     * nearest segment, rounded to a whole card. Never below 1: a day that holds nothing is not a
     * day.
     */
    public static function forMinutes(int $minutesPerDay): int
    {
        if (isset(self::ANCHORS[$minutesPerDay])) {
            return self::ANCHORS[$minutesPerDay];
        }

        $points = [];
        foreach (self::ANCHORS as $minutes => $terms) {
            $points[] = [$minutes, $terms];
        }

        // Which segment governs: the one containing the value, else the nearest end's.
        $last = count($points) - 1;
        $i = 0;
        while ($i < $last - 1 && $minutesPerDay > $points[$i + 1][0]) {
            $i++;
        }

        [$x0, $y0] = $points[$i];
        [$x1, $y1] = $points[$i + 1];
        $span = $x1 - $x0;
        if ($span === 0) {
            return max(1, $y0);   // two anchors at the same minute count: unreachable, not divided by
        }

        return max(1, (int) round($y0 + ($minutesPerDay - $x0) * (($y1 - $y0) / $span)));
    }

    /**
     * THE THREE NUMBERS ARE GONE, and this is where they were.
     *
     * `split()` turned a day's capacity into `phrases` / `chunks` / `words` — the three exact
     * counts P2 was handed and judged against. v0.4 has no arrays to count: a day is a SCENE with
     * shelves, the shelf sizes are guidance the prompt states, and a day that comes back with 22
     * units or 27 is a day rather than an error
     * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::SIZE_OUT_OF_RANGE}).
     *
     * What replaced it is one number and it belongs to the scene rather than to the minutes:
     * {@see SceneDay::UNITS}. The minutes still decide the SESSION — how many cards one sitting
     * deals — which is what {@see forMinutes()} above is still read for.
     */
}
