<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The counts a programme tab's brow is worded from: all units, walked, coming back tomorrow. */
final readonly class WindowSummaryView
{
    public function __construct(
        public int $total,
        public int $done,
        public int $returns,
    ) {}
}
