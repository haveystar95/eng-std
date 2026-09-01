<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\PlanTermArchiver;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Pause, abandon, complete.
 *
 * The difference that matters is what happens to the WORDS. A pause keeps the hold — «я вернусь»,
 * and coming back to a plan whose words were let go one at a time is coming back to a different
 * plan. Abandoning and completing both ARCHIVE it: the plan's reason comes off every pair it
 * claimed, and a pair with no other reason left leaves the pool with it.
 *
 * This reverses the rule these two endings used to follow — «words stay in the pool as ordinary
 * words», «18 слов ушли в общее повторение». The owner's ruling (01.09): a plan is a course with a
 * subject and a date, an ended one is an ARCHIVE, and its vocabulary does not become the learner's
 * daily queue by default. Everything is kept — the days, the cards, the results, the whole review
 * log — and what a learner wants out of it they add to «Учить» themselves. See
 * {@see PlanTermArchiver} for what «archive» does and does not touch.
 */
final readonly class EndPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermArchiver $archiver,
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

            // EXHAUSTIVE, with no `default` arm — the arm that used to be here quietly meant
            // «complete» and swallowed every string that was not one of the other two.
            match ($command->action) {
                PlanEnding::Pause => $plan->pause(),
                PlanEnding::Abandon => $plan->abandon($command->reason),
                PlanEnding::Complete => $plan->complete($this->clock->now()),
            };

            $this->plans->save($plan);

            if (! $command->action->keepsHold()) {
                $this->archiver->archivePlan($plan->userId(), $plan->id()->value);
            }
        });
    }
}
