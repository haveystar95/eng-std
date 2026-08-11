<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** The training dashboard's numbers, derived from progress + the append-only review log. */
final readonly class StatsView
{
    /**
     * @param list<string> $activeDays  local (user-timezone) calendar dates with >=1 review of ANY
     *                                   kind — study OR practice — within the activity window, oldest
     *                                   first. The source of truth for the activity calendar and the
     *                                   streak; the client renders it and never has to persist it.
     */
    public function __construct(
        public int $totalTerms,
        public int $learned,      // graduated to review
        public int $mastered,     // review with a long interval
        public int $dueToday,
        public int $reviewsToday,
        public int $streakDays,
        public array $activeDays = [],
        // The daily NEW-term quota and how much of it today has spent — the exact figures the
        // session builder uses to cap introductions (F13). `newRemaining = max(0, goal - today)`
        // is what a "Learn N" home CTA may introduce; 0 means the new-term limit is reached (reviews
        // are unaffected — they never spend this quota).
        public int $newGoal = 0,
        public int $newToday = 0,
    ) {}
}
