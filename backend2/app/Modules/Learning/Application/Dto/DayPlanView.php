<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * The simulated study day for one user on a target calendar date: which terms are due, which new
 * ones the quota would introduce, and the trainer each would arrive as. A dry run of the real
 * scheduler (GetDueTerms + ExerciseSelector) with no persistence — the admin day-simulator screen.
 */
final readonly class DayPlanView
{
    /** @param list<DayPlanEntryView> $entries */
    public function __construct(
        public string $date,          // Y-m-d, in the user's timezone
        public string $timezone,      // IANA zone the day boundary was computed in
        public int $dueCount,         // terms already in progress that are due on/before the date
        public int $newIntroduced,    // brand-new terms the day's remaining quota would introduce
        public int $newTermsPerDay,   // the user's configured daily new-term quota
        public array $entries,
    ) {}
}
