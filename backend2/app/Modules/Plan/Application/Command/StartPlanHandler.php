<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Exception\PlanAlreadyActive;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/** «Начать»: one live plan per learner, day 1 open from today. */
final readonly class StartPlanHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private LearnerCalendar $calendar,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(StartPlan $command): void
    {
        $now = $this->clock->now();
        $this->tx->run(function () use ($command, $now): void {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $live = $this->plans->findLiveFor($command->actorId);
            if ($live !== null && ! $live->id()->equals($plan->id())) {
                throw PlanAlreadyActive::holding($live->id());
            }
            $plan->start($now, $this->calendar->todayFor($command->actorId, $now));
            $this->plans->save($plan);
        });
    }
}
