<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Re-lays the calendar. A shorter plan drops scenes by the rule (inside the aggregate); a longer
 * one asks the model for the missing scenes with the existing ones as context.
 */
final readonly class ReschedulePlanHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(ReschedulePlan $command): void
    {
        $now = $this->clock->now();
        $today = $this->calendar->todayFor($command->actorId, $now);

        $outcome = $this->tx->run(function () use ($command, $today): array {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $eventDate = $command->clearEventDate ? null : ($command->eventDate ?? $plan->eventDate());
            $result = $plan->reschedule($eventDate, $command->daysTotal, $today, static fn (): PlanDayId => PlanDayId::generate());
            $this->plans->save($plan);

            $current = $plan->currentDay();
            $next = $current === null ? null : ($current->sceneId() !== null ? $current : $plan->nextSceneDayAfter($current->number()));
            $scene = $next === null ? null : $plan->sceneOf($next);

            return [$result['scenes_to_add'], $scene !== null && $scene->needsLesson() ? $scene->id() : null];
        });

        [$scenesToAdd, $lessonFor] = $outcome;
        if ($scenesToAdd > 0) {
            $this->dispatcher->buildPlan($command->planId, $scenesToAdd);
        }
        if ($lessonFor !== null) {
            $this->dispatcher->buildLesson($lessonFor);
        }
    }
}
