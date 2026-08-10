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
    ) {}
}
