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
    /**
     * @param  list<WindowStageView>  $stages
     * @param  list<WindowListeningView>  $listening  the questions about the day's whole visit (GEN-2a, additive)
     * @param  list<string>  $highlights  «Что было хорошо» — two or three lines of a passed day (кадр 37-13,
     *   наряд CONV-1), written by the server and printed by the client; empty until the day is passed
     * @param  list<WindowSourceView>  $sources  the scenes the day is made of, in the order of the route (наряд BACK-TAILS-2 §4)
     */
    public function __construct(
        public WindowDayView $day,
        public array $stages,
        public float $dayProgress,
        public WindowProgramView $program,
        public ?string $allowedAction,
        public array $listening = [],
        public array $highlights = [],
        public array $sources = [],
    ) {}
}
