<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The user's personal median answer time for one exercise mode — computed over correct,
 * non-practice answers only, and cached per (user, mode). It is deliberately NOT recomputed
 * per answer: a 50-answer batch must not recompute the percentile 50 times. The write side
 * invalidates the cache after folding a batch in, so the next read reflects the new answers.
 */
interface LatencyMedianReader
{
    public function medianFor(UserId $user, ExerciseMode $mode): LatencyBaseline;

    /** Drop the cached median after new answers land, so it is recomputed lazily on next read. */
    public function forget(UserId $user, ExerciseMode $mode): void;
}
