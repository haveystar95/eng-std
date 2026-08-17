<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A user's simulated study day, as the admin panel renders it. */
final readonly class AdminDayPlanView
{
    /** @param list<AdminDayPlanEntry> $entries */
    public function __construct(
        public string $date,
        public string $timezone,
        public int $dueCount,
        public int $newIntroduced,
        public int $newTermsPerDay,
        public array $entries,
    ) {}
}
