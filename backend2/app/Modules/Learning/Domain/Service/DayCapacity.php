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
     * THE DAY'S THREE NUMBERS — lines, connectors, words — and they sum to the budget exactly.
     *
     * P2 v0.2 asks for three arrays and is handed three exact counts. Not a band: two prompt
     * iterations of «сделай 40–50% реплик» produced 31.3% on a sixteen-term day, and the same
     * mechanic stated as arithmetic produced exactly the figure asked for on the first run
     * (docs/research/plan-sandbox-2026-08-29.md §4). A band is a request; a number is a check.
     *
     *   phrases  `ceil(0.55 × cap)` — the LINES. The day is a conversation, and this is the
     *            majority of it. Under a third replies and it is a vocabulary list with an event
     *            date attached, which is what v0 produced.
     *   chunks   `max(1, floor(0.15 × cap))` — the CONNECTORS («deal with», «be in charge of»).
     *            At least one, always: a day with no connector teaches words that sit in a slot
     *            and nothing that joins two of them.
     *   words    whatever is left. The remainder goes HERE and not to the lines, because the
     *            other two are the ones with a floor to respect.
     *
     * 14 → 8 + 2 + 4. 7 → 4 + 1 + 2. 24 → 14 + 3 + 7.
     *
     * Beside the table on purpose: the three numbers are a function OF the capacity, and the one
     * way they can go wrong is by being computed somewhere the table is not.
     *
     * @return array{phrases: int, chunks: int, words: int}
     */
    public static function split(int $termBudget): array
    {
        $phrases = (int) ceil(self::PHRASE_SHARE * $termBudget);
        // At least one connector, unless the budget is so small that one would leave no line —
        // a day is a conversation before it is anything else, so the line is the last to give way.
        $chunks = min(max(1, (int) floor(self::CHUNK_SHARE * $termBudget)), max(0, $termBudget - 1));

        $phrases = min($phrases, max(1, $termBudget - $chunks - 1));
        $words = $termBudget - $phrases - $chunks;

        return ['phrases' => $phrases, 'chunks' => $chunks, 'words' => max(0, $words)];
    }

    /** The share of a day that is spoken turns. See {@see split()}. */
    private const PHRASE_SHARE = 0.55;

    /** The share of a day that is phrasal verbs and fixed collocations. */
    private const CHUNK_SHARE = 0.15;
}
