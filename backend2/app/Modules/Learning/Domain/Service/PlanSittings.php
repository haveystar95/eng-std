<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * ПРИСЕСТЫ — how a day's sitting is cut into the pieces a person actually sits down for.
 *
 * The learner chose 10, 20 or 40 minutes, and until now that number CUT THE DAY: the session was
 * built to the budget and everything past it was simply not dealt, so a 10-minute day of a scene
 * with twenty-five units could not be finished at all — the tail was rebuilt from scratch on the
 * next visit, at whatever the ladder said by then. The budget is not a limit on the day. It is the
 * length of one присест (наряд SIT-1, Ч-6).
 *
 * So the day is dealt WHOLE and this says where the breaks are.
 *
 * ## The cut is only ever on a section boundary
 *
 * A присест is «about the budget», not exactly it, and the reason is the sections
 * ({@see PlanSessionSections}): stopping in the middle of «Ты ответишь» leaves the learner with
 * half a shelf and a screen that has to explain why. So cards are added part by part, and a part is
 * never split. A part LONGER than the whole budget is its own присест — one oversized sitting is
 * honest, and the alternative is a rule that cuts inside a section after all.
 *
 * ## Nothing here is a limit on the day
 *
 * Every task is in exactly one присест and every присест is non-empty, so `array_sum()` of the
 * result is the number of tasks it was given. That is the property the client's progress bar rests
 * on: «пройденное не сгорает» is only true if the parts add up to the whole.
 */
final class PlanSittings
{
    /**
     * THE CEILING ON ONE ПРИСЕСТ, whatever the minutes buy.
     *
     * Forty cards, and it is not a second budget — it is the point past which a sitting has stopped
     * being one. The minutes decide the length a learner ASKED for; this decides the length a person
     * can actually do in a row, and the two only disagree when the per-card estimate is generous.
     *
     * It exists because the budget alone never once cut a sitting in the whole E2E-SIM-2 run
     * (С-12): `sittings` came back `[68]`, `[81]`, `[73]`, `[53]` — always one присест for the whole
     * day, and the «присест пройден» screen was never seen. Even with the honest 16 s a card
     * ({@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler}), twenty minutes buys
     * 75 cards, which is still more than a day-scene holds — so the mechanism would have gone on
     * being switched off by arithmetic. A day-scene of 68 tasks is two sittings for anybody.
     *
     * A SECTION longer than this is still its own присест: the cut is only ever on a section
     * boundary, and «сорок» does not outrank «не резать посреди „Ты ответишь“».
     */
    public const MAX_TASKS_PER_SITTING = 40;

    /**
     * The task counts of each присест, in order.
     *
     * @param  list<string>  $sections  one section key per task, in the order the tasks are dealt
     * @param  int  $budget  how many cards fit in the learner's chosen minutes; at least 1
     * @return list<int>
     */
    public static function cut(array $sections, int $budget): array
    {
        if ($sections === []) {
            return [];
        }
        // The learner's minutes, and never more than a person sits through in one go.
        $budget = min(max(1, $budget), self::MAX_TASKS_PER_SITTING);

        // The parts, in the order they arrive — sizes only, because the cut is between them.
        /** @var list<int> $parts */
        $parts = [];
        $current = null;
        $size = 0;
        foreach ($sections as $section) {
            if ($current !== null && $section !== $current) {
                $parts[] = $size;
                $size = 0;
            }
            $current = $section;
            $size++;
        }
        // The last part is always open — the list was checked non-empty above — so it is closed
        // unconditionally rather than behind a test that can only ever be true.
        $parts[] = $size;

        /** @var list<int> $sittings */
        $sittings = [];
        $open = 0;
        foreach ($parts as $size) {
            // Adding this part would overrun the budget, and there is already something to hand
            // over: close the присест here. An EMPTY присест never happens, which is what makes an
            // oversized part its own sitting rather than a sitting of nothing followed by it.
            if ($open > 0 && $open + $size > $budget) {
                $sittings[] = $open;
                $open = 0;
            }
            $open += $size;
        }
        if ($open > 0) {
            $sittings[] = $open;
        }

        return $sittings;
    }
}
