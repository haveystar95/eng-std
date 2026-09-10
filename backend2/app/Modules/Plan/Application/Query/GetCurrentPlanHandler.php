<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class GetCurrentPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanViews $views,
        private LearnerCalendar $calendar,
        private Clock $clock,
    ) {}

    public function __invoke(GetCurrentPlan $query): ?PlanView
    {
        $plan = $this->plans->findLiveFor($query->actorId);
        if ($plan === null) {
            return null;
        }

        return $this->views->plan($plan, $this->calendar->todayFor($query->actorId, $this->clock->now()));
    }
}
