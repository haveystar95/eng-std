<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The counts a programme tab's brow is worded from: all units, walked, and how many of them came back from earlier days (наряд FIX-3 §9). */
final readonly class WindowSummaryView
{
    public function __construct(
        public int $total,
        public int $done,
        public int $returns,
    ) {}
}
