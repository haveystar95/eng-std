<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\Grade;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns "the user graded this term X at time T" into the term's next progress state.
 * Pure: no DB, no clock of its own, no randomness it wasn't handed. That is what makes
 * scheduling unit-testable and keeps the logic honest.
 *
 * Start with {@see Sm2Scheduler}; the interface lets an FsrsScheduler replace it later
 * without touching any handler — FSRS is measurably better but needs review history to
 * fit its parameters, which isn't available on day one.
 */
interface Scheduler
{
    /**
     * @param  DateTimeZone|null  $userZone  the user's calendar zone; a day-scale next-due date is
     *                                       floored to the start of the user's day in it (F19).
     *                                       Null = UTC (the documented fallback for an unknown zone).
     */
    public function schedule(TermProgress $progress, Grade $grade, DateTimeImmutable $now, ?DateTimeZone $userZone = null): TermProgress;
}
