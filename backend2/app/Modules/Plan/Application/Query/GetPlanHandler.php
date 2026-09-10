<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class GetPlanHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanViews $views,
        private LearnerCalendar $calendar,
        private Clock $clock,
    ) {}

    public function __invoke(GetPlan $query): PlanView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);

        return $this->views->plan($plan, $this->calendar->todayFor($query->actorId, $this->clock->now()));
    }
}
