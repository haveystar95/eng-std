<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * A day landed. Three things happen and their order is the design.
 *
 * 1. **The day is marked ready** and given its collection.
 * 2. **Its terms are enrolled, strictly** — `enrollment_sources += plan:<id>`. This is the moment
 *    the plan starts holding words, and it happens per DAY rather than at the start of the plan,
 *    because a word cannot be held before it exists.
 * Step 3 is the one PLAN-1b changed, and it now depends on how long the plan is
 * ({@see PlanGenerationPolicy}). On a SHORT plan the chain continues here, on `ready`: nobody is
 * waiting on a decision, and the plan is meant to arrive written. On a LONG one nothing is queued
 * here at all — «ready» means the material exists and says nothing about whether the learner will
 * come back, and queueing on it meant a fourteen-day plan wrote all fourteen days the afternoon it
 * started. There the next day waits for this one to be DONE, which happens in the session path
 * ({@see BuildPlanSessionHandler}).
 *
 * On FAILURE nothing is enrolled and nothing is queued. A day that failed its first attempt goes
 * back to `pending` and the dispatcher is asked again immediately: that is the one re-run the
 * budget allows. After the second it is `failed`, the reason is stored, and the plan stops — a
 * silent third attempt is how a broken prompt spends a plan's whole budget on one day.
 *
 * Since v0.3 that re-run is the ONLY one there is. `PlanDayComposer` used to make a second call of
 * its own inside a single claim, so this «one re-run» was really the third and fourth paid calls,
 * and a live day cost $0.197 against a budget written for two ({@see \App\Modules\Learning\Domain\Entity\PlanDay}).
 */
final readonly class FinishPlanDayHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private EnrollTermHandler $enroll,
        private DispatchesPlanDay $dispatcher,
        private TransactionManager $tx,
    ) {}

    public function __invoke(FinishPlanDay $command): void
    {
        /** @var array{retry: bool, next: int|null} $outcome */
        $outcome = $this->tx->run(function () use ($command): array {
            $planId = PlanId::fromString($command->planId);
            $plan = $this->plans->findById($planId);
            $day = $this->days->findByIndexForUpdate($planId, $command->dayIndex);
            if ($plan === null || $day === null) {
                return ['retry' => false, 'next' => null];
            }

            if ($command->failReason !== null || $command->collectionId === null) {
                // The verdict lands twice: as prose in `fail_reason` for the plan screen, and as a
                // list that ACCUMULATES across attempts for the next prompt to read.
                $day->markFailed(
                    $command->failReason ?? 'день вернулся без коллекции',
                    $command->failViolations,
                    $command->repairCalls,
                );
                $this->days->save($day);

                // Back to `pending` means an attempt is left; the queue is asked again rather than
                // waiting for the learner to notice.
                return ['retry' => ! $day->isReady() && $day->generationAttempts() < \App\Modules\Learning\Domain\Entity\PlanDay::MAX_ATTEMPTS, 'next' => null];
            }

            // The repair is charged on the WRITTEN day too. A day that was patched into shape cost
            // two calls whether or not the patch worked, and the row has to say so.
            $day->markReady(CollectionId::fromString($command->collectionId), $command->repairCalls);
            $this->days->save($day);

            // STRICT ENROLMENT. Through the ordinary enrolment command, with the plan named as the
            // reason — so a word the learner already had keeps its original enrolment moment and
            // simply gains a second reason. Nothing here is a special path into the pool.
            $source = EnrollmentSources::forPlan($plan->id()->value);
            foreach ($command->termIds as $termId) {
                ($this->enroll)(new EnrollTerm($plan->userId(), TermId::fromString($termId), $source));
            }

            // A SHORT plan continues here, on `ready`: there is no learner in the loop yet and the
            // whole point is that it arrives written. A long one is chained from the session path,
            // when a day is actually walked. Either way ONE day at a time — day n+1 is written from
            // day n's terms, so it cannot be written beside it.
            $computed = $plan->computed();
            $introDays = is_int($computed['intro_days'] ?? null) ? $computed['intro_days'] : 1;

            return [
                'retry' => false,
                'next' => PlanGenerationPolicy::nextAfterReady(
                    $this->days->listForPlan($planId),
                    $command->dayIndex,
                    $introDays,
                ),
            ];
        });

        // Outside the transaction: a worker can pick a job up before the commit lands.
        if ($outcome['retry']) {
            $this->dispatcher->dispatchDay($command->planId, $command->dayIndex);

            return;
        }
        if ($outcome['next'] !== null) {
            $this->dispatcher->dispatchDay($command->planId, $outcome['next']);
        }
    }
}
