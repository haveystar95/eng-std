<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Plan\Application\Inspection\PlanInspection;

/** Asks the plan's owner for its journal of calls (наряд ADM-1). */
final readonly class GetPlanCallsHandler
{
    public function __construct(
        private PlanInspection $plans,
        private GetPlanPageHandler $pages,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}|null
     *
     * @throws PlanCodeConflict
     */
    public function __invoke(GetPlanCalls $query): ?array
    {
        $planId = $this->pages->resolve($query->code);

        return $planId === null ? null : $this->plans->calls($planId, $query->day, $query->source, $query->cursor, $query->limit);
    }
}
