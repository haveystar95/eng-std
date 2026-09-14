<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The day's numbers the tab's plate prints: «75 карточек · 19 минут». */
final readonly class DayMetricsView
{
    public function __construct(
        public int $cardsTotal,
        public int $minutesSpent,
    ) {}
}
