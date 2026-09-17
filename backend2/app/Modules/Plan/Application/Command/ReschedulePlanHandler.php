<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanEventJournal;
use App\Modules\Plan\Application\Service\PlanNotifier;
use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\PlanEventRules;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Re-lays the calendar. A shorter plan drops scenes by the rule (inside the aggregate); a longer
 * one asks the model for the missing scenes with the existing ones as context.
 *
 * A route that came out SHORTER is written to the plan's journal as `days_skipped_rebuilt`
 * `{from, to}` in the same transaction ({@see PlanEventRules::forReschedule()}) — the only rebuild
 * the server has; it does not detect skipped days on its own. The letter follows the commit.
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
        private PlanEventJournal $journal,
        private PlanNotifier $notifier,
    ) {}

    public function __invoke(ReschedulePlan $command): void
    {
        $now = $this->clock->now();
        $today = $this->calendar->todayFor($command->actorId, $now);

        /** @var PlanEvent|null $rebuilt */
        $rebuilt = null;
        $outcome = $this->tx->run(function () use ($command, $today, &$rebuilt): array {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $daysBefore = $plan->daysTotal();
            $eventDate = $command->clearEventDate ? null : ($command->eventDate ?? $plan->eventDate());
            $result = $plan->reschedule($eventDate, $command->daysTotal, $today, static fn (): PlanDayId => PlanDayId::generate());
            $this->plans->save($plan);
            $kind = PlanEventRules::forReschedule($daysBefore, $plan->daysTotal());
            if ($kind !== null) {
                $rebuilt = $this->journal->record($plan->id(), $plan->userId(), $kind, payload: ['from' => $daysBefore, 'to' => $plan->daysTotal()]);
            }

            // Only the day the learner is on may lack its lesson here (a scene laid on it); the days after it get theirs when
            // the day before them closes (наряд GEN-3 §11).
            return [$result['scenes_to_add'], $plan->currentSceneWithoutLesson()?->id()];
        });

        [$scenesToAdd, $lessonFor] = $outcome;
        if ($scenesToAdd > 0) {
            $this->dispatcher->buildPlan($command->planId, $scenesToAdd);
        }
        if ($lessonFor !== null) {
            $this->dispatcher->buildLesson($lessonFor);
        }
        $this->notifier->notify($rebuilt);
    }
}
