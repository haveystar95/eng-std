<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\PlanSpend;

/**
 * The plan's own ledger row — what a model call for a plan cost, written where the collection
 * ledger already lives (`generation_requests`, `purpose = 'plan'`).
 *
 * A port and not a repository because there is no entity here: a plan spend row is written once,
 * never transitions, and is only ever read by a cost screen. The seam exists so the thing that
 * makes the paid call can be tested against a writer that refuses.
 *
 * IMPLEMENTATIONS MUST NOT SWALLOW A FAILURE. See
 * {@see \App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded}.
 */
interface RecordsPlanSpend
{
    /** @throws \App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded */
    public function record(PlanSpend $spend): void;
}
