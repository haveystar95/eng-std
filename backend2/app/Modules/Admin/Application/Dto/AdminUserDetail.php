<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The full admin view of one user: profile, progress (raw state breakdown plus the Mastery-defined
 * mastered/learned counts from the Learning module), review activity, AI spend, and their library.
 */
final readonly class AdminUserDetail
{
    /** @param list<AdminUserCollectionRow> $collections */
    public function __construct(
        public AdminUserProfileRow $profile,
        public ProgressStateCounts $states,
        public int $mastered,
        public int $learned,
        public int $dueToday,
        public int $reviewsTotal,
        public int $reviewsToday,
        public int $streakDays,
        public UserCostBreakdown $costs,
        public array $collections,
    ) {}
}
