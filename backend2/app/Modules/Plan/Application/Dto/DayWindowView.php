<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * «ОКНО ДНЯ» (DAY-UI-2, кадры 23-0a…0d) — everything the window draws, counted here: the day, its
 * stage rows, the progress bar of the compact header, the three programme tabs with the counts
 * their brows are written from, and the one action. The client words it and counts nothing.
 */
final readonly class DayWindowView
{
    /** @param list<WindowStageView> $stages */
    public function __construct(
        public WindowDayView $day,
        public array $stages,
        public float $dayProgress,
        public WindowProgramView $program,
        public ?string $allowedAction,
    ) {}
}
