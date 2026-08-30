<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Exception\PlanAlreadyActive;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * The moment the plan stops being a document and becomes a mechanism: it starts holding words, and
 * day 1 goes into the queue.
 *
 * ONE day is queued, not all of them. Day 2 is dispatched when day 1 is ready
 * ({@see FinishPlanDayHandler}), for three reasons in the order they bite: day 2 is written with
 * day 1's terms in its KNOWN block and cannot exist before them; a fan-out spends the whole plan's
 * money before the learner has looked at one day; and a plan whose day 1 came back broken should
 * stop rather than produce four more broken days.
 */
final readonly class StartPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private DispatchesPlanDay $dispatcher,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(StartPlan $command): void
    {
        $firstDayIndex = $this->tx->run(function () use ($command): ?int {
            $plan = $this->plans->findForUpdate($command->planId);
            if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
                throw PlanNotFound::withId($command->planId->value);
            }

            // The database holds the same rule with a partial unique index; this exists so the
            // learner gets a sentence and a plan id instead of a constraint violation.
            $running = $this->plans->findActiveFor($plan->userId());
            if ($running !== null && ! $running->id()->equals($plan->id())) {
                throw PlanAlreadyActive::make($running->id()->value);
            }

            $plan->start($this->clock->now());
            $this->plans->save($plan);

            foreach ($this->days->listForPlan($plan->id()) as $day) {
                if ($day->kind() === PlanDayKind::Intro && ! $day->isReady()) {
                    return $day->dayIndex();
                }
            }

            return null;
        });

        // AFTER the transaction: a job dispatched inside one can be picked up by a worker before
        // the commit lands, and then it reads a plan that is still a draft.
        if ($firstDayIndex !== null) {
            $this->dispatcher->dispatchDay($command->planId->value, $firstDayIndex);
        }
    }
}
