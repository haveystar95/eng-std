<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Exception\PlanAlreadyActive;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * The moment the plan stops being a document and becomes a mechanism: it starts holding words, and
 * its FIRST day goes into the queue.
 *
 * One day, on every plan, however short. What differs is what carries the chain on
 * ({@see PlanGenerationPolicy}): a short plan continues on `ready`, so it arrives whole without a
 * learner in the loop; a long one waits for `done`, so it never pays for days nobody reached.
 * Neither branch fans out, because day n is written FROM days 1…n−1 — a fan-out is not a faster way
 * to walk that sequence, it is a way to not walk it, and the live S1 run proved it by writing day 2
 * one second after day 1 and giving it an empty KNOWN block.
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
        $firstDay = $this->tx->run(function () use ($command): ?int {
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

            return PlanGenerationPolicy::firstDayToQueue($this->days->listForPlan($plan->id()));
        });

        // AFTER the transaction: a job dispatched inside one can be picked up by a worker before
        // the commit lands, and then it reads a plan that is still a draft.
        if ($firstDay !== null) {
            $this->dispatcher->dispatchDay($command->planId->value, $firstDay);
        }
    }
}
