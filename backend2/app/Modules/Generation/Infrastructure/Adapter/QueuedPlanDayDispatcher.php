<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Infrastructure\Job\GeneratePlanDayJob;
use App\Modules\Learning\Application\Port\DispatchesPlanDay;

/**
 * Learning's day-dispatch port, fulfilled by Generation's queue — the same direction as
 * {@see QueuedTermEnrichmentDispatcher} fulfils Vocabulary's.
 *
 * No config switch here, unlike the enrichment chain. That switch exists because the станок adds a
 * model call to every generation and its cost has to be stoppable without a deploy; a plan day is
 * not an addition to anything — it IS the plan, and a plan with the day generation switched off is
 * a plan that shows the learner an empty day. The thing that stops plans is the mode flag on the
 * feature, not a gate inside its own machinery.
 */
final class QueuedPlanDayDispatcher implements DispatchesPlanDay
{
    public function dispatchDay(string $planId, int $dayIndex): void
    {
        GeneratePlanDayJob::dispatch($planId, $dayIndex);
    }
}
