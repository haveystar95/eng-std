<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A whole plan's standing: every day's words worked out, and which day the learner is ON.
 *
 * The FOCUS is the load-bearing number. It is the first introduction day that has not been passed,
 * and everything the plan does hangs off it: which session is strict, which day may be generated
 * next, what the screen calls «сегодня». It is DERIVED here rather than stored, for the same reason
 * the stages are — `learning_plan_days.status` is a cache the command path writes, and a cache that
 * disagreed with the log would leave a learner on a day they had already finished.
 */
final readonly class PlanProgressView
{
    /**
     * @param  array<int, PlanDayProgressView>  $days  keyed by day index, introduction days only
     */
    public function __construct(
        public array $days,
        /**
         * The first unpassed introduction day. When every day is passed this is the FINAL day's
         * index — the plan has nothing left to teach and what remains is the rehearsal.
         */
        public int $focusDayIndex,
        /** The learner's local day, `Y-m-d` — what «после ночи» was measured against. */
        public string $today,
    ) {}

    /**
     * Every plan term's standing, flattened.
     *
     * @return array<string, \App\Modules\Learning\Domain\ValueObject\PlanTermStanding>
     */
    public function allStandings(): array
    {
        $out = [];
        foreach ($this->days as $day) {
            foreach ($day->standings as $termId => $standing) {
                $out[$termId] = $standing;
            }
        }

        return $out;
    }
}
