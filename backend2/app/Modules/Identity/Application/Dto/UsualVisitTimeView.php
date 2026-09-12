<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** The learner's usual time of day, in their own zone. `visitsCounted = 0` means the 19:00 default. */
final readonly class UsualVisitTimeView
{
    public function __construct(
        public int $minutesOfDay,
        public string $timezone,
        public int $visitsCounted,
    ) {}

    /** «19:00». */
    public function hhmm(): string
    {
        return sprintf('%02d:%02d', intdiv($this->minutesOfDay, 60), $this->minutesOfDay % 60);
    }
}
