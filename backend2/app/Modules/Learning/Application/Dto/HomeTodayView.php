<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * What today actually produced — the evening screen's «32 из 32 · 6 мин 40 с».
 *
 * STUDY answers only. Free practice is activity and keeps the streak, but it is not the day's plan
 * and counting it here would let a practice run close a day whose repeats were never touched
 * (the `practice` vs `study` glossary in the root CLAUDE.md). That is also why this is not
 * `/stats.reviews_today`, which counts answers of BOTH kinds on purpose.
 */
final readonly class HomeTodayView
{
    public function __construct(
        public int $answered,
        /** Sum of the answers' own latencies, in seconds — time spent answering, not time on screen. */
        public int $seconds,
    ) {}
}
