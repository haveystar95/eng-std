<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * The tab's plan: the live one, or — when nothing is running — the newest one that is built and
 * waiting for «Начать». A `ready` plan is a state the screen draws (days locked, no start date),
 * not an absence: leaving it out of here was a plan the learner had paid for disappearing from
 * the app with no way back to it.
 */
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
        $plan = $this->plans->findCurrentFor($query->actorId);
        if ($plan === null) {
            return null;
        }

        return $this->views->plan($plan, $this->calendar->todayFor($query->actorId, $this->clock->now()));
    }
}
