<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * ПРИСЕСТЫ — how a day's sitting is cut into the pieces a person actually sits down for.
 *
 * ## ДЕНЬ = ОДИН ПРИСЕСТ, ПРОГОН СЦЕНЫ — ВТОРОЙ (наряд DAY-FIX-2, Ч.2.1)
 *
 * It used to be cut by the learner's minutes, on section boundaries, with a ceiling of forty. The
 * minutes are gone from the cut: the owner's rule is that a DAY is one sitting of at most
 * {@see MAX_TASKS_PER_SITTING} cards, and the only thing that earns a second sitting is the прогон
 * сцены — ступень C, which is a different act (the microphone, nothing on the screen) and which
 * the learner is right to want a breath before. Everything else the planner has already trimmed to
 * fit ({@see \App\Modules\Learning\Application\Service\PlanSittingPlanner}), so a first sitting
 * longer than the ceiling is a bug there, not a case here.
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
     * THE CEILING ON ONE ПРИСЕСТ — «день ≤ 40 карточек» (решение владельца 05.09).
     *
     * Forty is the point past which a sitting has stopped being one: the stand's own day-scene
     * came back at 68, and the live day 2 of 05.09 at 50–61, and neither was finished in an
     * evening. Read from `config/learning.php → plan.budget.sitting_max_cards` by the planner; this
     * constant is the domain's own statement of the same number, and the two are pinned together
     * by a test.
     */
    public const MAX_TASKS_PER_SITTING = 40;

    /**
     * The task counts of each присест, in order: everything before the прогон, then the прогон.
     *
     * @param  list<string>  $sections  one section key per task, in the order the tasks are dealt
     * @return list<int>
     */
    public static function split(array $sections): array
    {
        if ($sections === []) {
            return [];
        }

        $day = 0;
        $run = 0;
        foreach ($sections as $section) {
            if (self::isSceneRun($section)) {
                $run++;
            } else {
                $day++;
            }
        }

        $out = [];
        if ($day > 0) {
            $out[] = $day;
        }
        if ($run > 0) {
            $out[] = $run;
        }

        return $out;
    }

    /** A section key of the прогон — `scene_run#<day>`, as the planner keys it. */
    private static function isSceneRun(string $section): bool
    {
        return $section === PlanSessionSections::SCENE_RUN
            || str_starts_with($section, PlanSessionSections::SCENE_RUN . '#');
    }
}
