<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanSummaryView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanListReader;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class ListPlansHandler
{
    public function __construct(
        private PlanListReader $plans,
        private PlanViews $views,
        private LearnerCalendar $calendar,
        private Clock $clock,
    ) {}

    /** @return list<PlanSummaryView> */
    public function __invoke(ListPlans $query): array
    {
        $today = $this->calendar->todayFor($query->actorId, $this->clock->now());

        return array_map(fn ($plan): PlanSummaryView => $this->views->summary($plan, $today), $this->plans->allFor($query->actorId));
    }
}
