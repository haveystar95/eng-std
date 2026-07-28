<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** The training dashboard's numbers, derived from progress + the daily stats projection. */
final readonly class StatsView
{
    public function __construct(
        public int $totalTerms,
        public int $learned,      // graduated to review
        public int $mastered,     // review with a long interval
        public int $dueToday,
        public int $reviewsToday,
        public int $streakDays,
    ) {}
}
