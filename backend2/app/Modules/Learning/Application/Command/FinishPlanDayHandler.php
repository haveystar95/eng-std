<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
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
 * The next day is NOT queued here, and that is the change PLAN-1b made. «Ready» means the material
 * exists; it says nothing about whether the learner will come back, and queueing on it meant a
 * fourteen-day plan wrote all fourteen days the afternoon it started. The next day is queued when
 * this one is DONE — walked, its every word through stage A — which happens in the session path
 * ({@see BuildPlanSessionHandler}) and is governed by {@see PlanGenerationPolicy}. A SHORT plan is
 * the exception and needs nothing here either: its days were all queued at the start.
 *
 * On FAILURE nothing is enrolled and nothing is queued. A day that failed its first attempt goes
 * back to `pending` and the dispatcher is asked again immediately: that is the one re-run the
 * budget allows. After the second it is `failed`, the reason is stored, and the plan stops — a
 * silent third attempt is how a broken prompt spends a plan's whole budget on one day.
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
                $day->markFailed($command->failReason ?? 'день вернулся без коллекции');
                $this->days->save($day);

                // Back to `pending` means an attempt is left; the queue is asked again rather than
                // waiting for the learner to notice.
                return ['retry' => ! $day->isReady() && $day->generationAttempts() < \App\Modules\Learning\Domain\Entity\PlanDay::MAX_ATTEMPTS, 'next' => null];
            }

            $day->markReady(CollectionId::fromString($command->collectionId));
            $this->days->save($day);

            // STRICT ENROLMENT. Through the ordinary enrolment command, with the plan named as the
            // reason — so a word the learner already had keeps its original enrolment moment and
            // simply gains a second reason. Nothing here is a special path into the pool.
            $source = EnrollmentSources::forPlan($plan->id()->value);
            foreach ($command->termIds as $termId) {
                ($this->enroll)(new EnrollTerm($plan->userId(), TermId::fromString($termId), $source));
            }

            return ['retry' => false, 'next' => null];
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
