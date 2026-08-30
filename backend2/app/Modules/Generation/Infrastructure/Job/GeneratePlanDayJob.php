<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\GeneratePlanDay;
use App\Modules\Generation\Application\Command\GeneratePlanDayHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One day of one plan.
 *
 * `tries = 1`, and that is the whole point. The handler already owns the retry policy: the day row
 * counts CLAIMS, allows two, and records the reason on each failure — because a day that came back
 * failing the validator will fail the same way on a mechanical retry, and each attempt is a paid
 * model call. A queue `tries = 3` on top of that would turn one bad prompt into six calls and would
 * hide the reason behind whichever attempt happened to die last.
 *
 * What the queue is still good for is the crash the handler cannot catch — a worker killed
 * mid-call. That case leaves the day `generating` with one attempt spent, which is correct: it has
 * one more, and the next dispatch takes it.
 */
final class GeneratePlanDayJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Two model calls at up to 180s each, plus the writes. */
    public int $timeout = 420;

    public function __construct(
        private readonly string $planId,
        private readonly int $dayIndex,
    ) {}

    public function handle(GeneratePlanDayHandler $handler): void
    {
        $handler(new GeneratePlanDay($this->planId, $this->dayIndex));
    }

    public function failed(Throwable $e): void
    {
        // The handler reports its own failures onto the day row; reaching here means it did not get
        // the chance to — a timeout, a killed worker, an error outside the try. The day is left
        // `generating` with an attempt spent, and the log is the only record of why.
        Log::error('plan day generation job died', [
            'plan_id' => $this->planId,
            'day_index' => $this->dayIndex,
            'error' => $e->getMessage(),
        ]);
    }
}
