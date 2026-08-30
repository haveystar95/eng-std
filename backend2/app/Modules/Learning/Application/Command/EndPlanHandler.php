<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\PlanTermReleaser;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Pause, abandon, complete.
 *
 * The difference that matters is what happens to the WORDS. A pause keeps the hold — «я вернусь»,
 * and coming back to a plan whose words were let go one at a time is coming back to a different
 * plan. Abandoning and completing both release it: the plan's reason comes off every pair it
 * claimed, and the words stay in the pool as ordinary words, with their rung, their schedule and
 * their history intact.
 *
 * Releasing is not unenrolling. The learner spent days on these words; a plan ending is not a
 * reason to stop studying them, it is a reason to stop refusing to let them stop.
 */
final readonly class EndPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermReleaser $releaser,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(EndPlan $command): void
    {
        $this->tx->run(function () use ($command): void {
            $plan = $this->plans->findForUpdate($command->planId);
            if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
                throw PlanNotFound::withId($command->planId->value);
            }

            match ($command->action) {
                EndPlan::PAUSE => $plan->pause(),
                EndPlan::ABANDON => $plan->abandon(),
                default => $plan->complete($this->clock->now()),
            };

            $this->plans->save($plan);

            if ($command->action !== EndPlan::PAUSE) {
                $this->releaser->releasePlan($plan->userId(), $plan->id()->value);
            }
        });
    }
}
