<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * Creates the plan row with its calendar and queues the model call. The HTTP request never
 * waits on the model; the client polls the build.
 */
final readonly class CreatePlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private Clock $clock,
    ) {}

    public function __invoke(CreatePlan $command): PlanId
    {
        $now = $this->clock->now();
        $plan = Plan::create(
            id: PlanId::generate(),
            userId: $command->actorId,
            goalText: $command->goalText,
            targetLang: $command->targetLang,
            nativeLang: $this->calendar->nativeLangFor($command->actorId),
            level: $command->level,
            daysRequested: $command->daysTotal,
            eventDate: $command->eventDate,
            today: $this->calendar->todayFor($command->actorId, $now),
            now: $now,
            dayIds: static fn (): PlanDayId => PlanDayId::generate(),
        );

        $this->plans->save($plan);
        $this->dispatcher->buildPlan($plan->id());

        return $plan->id();
    }
}
