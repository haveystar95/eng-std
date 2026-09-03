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
        $budget = max(1, $budget);

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
