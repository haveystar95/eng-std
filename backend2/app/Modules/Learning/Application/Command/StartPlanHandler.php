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
 * its first day — or, on a short plan, all of them — goes into the queue.
 *
 * WHICH of those is {@see PlanGenerationPolicy}'s decision and not this handler's. Three days or
 * fewer are written whole, because there is no meaningful abandonment window in a three-day plan
 * and the alternative is a spinner on day 2; anything longer is written one day at a time, with the
 * next queued when the previous is DONE. Both halves of that split exist for the same reason: a day
 * is a paid model call, and the plan's whole budget must not be spent before the learner has looked
 * at any of it.
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
        /** @var list<int> $toQueue */
        $toQueue = $this->tx->run(function () use ($command): array {
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

            $computed = $plan->computed();
            $introDays = is_int($computed['intro_days'] ?? null) ? $computed['intro_days'] : 1;

            return PlanGenerationPolicy::daysToQueueAtStart(
                $this->days->listForPlan($plan->id()),
                $introDays,
            );
        });

        // AFTER the transaction: a job dispatched inside one can be picked up by a worker before
        // the commit lands, and then it reads a plan that is still a draft.
        foreach ($toQueue as $dayIndex) {
            $this->dispatcher->dispatchDay($command->planId->value, $dayIndex);
        }
    }
}
