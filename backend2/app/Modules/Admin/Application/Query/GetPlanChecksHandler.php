<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\PlanCheckRow;
use App\Modules\Plan\Application\Dto\CheckCounterRow;
use App\Modules\Plan\Application\Query\GetCheckCounters;
use App\Modules\Plan\Application\Query\GetCheckCountersHandler;

/** Delegates to the Plan module's own query and maps into an admin-owned row, so the boundary holds. */
final readonly class GetPlanChecksHandler
{
    public function __construct(private GetCheckCountersHandler $counters) {}

    /** @return list<PlanCheckRow> */
    public function __invoke(GetPlanChecks $query): array
    {
        return array_map(
            static fn (CheckCounterRow $r): PlanCheckRow => new PlanCheckRow($r->promptVersion, $r->check, $r->action, $r->hits),
            ($this->counters)(new GetCheckCounters),
        );
    }
}
