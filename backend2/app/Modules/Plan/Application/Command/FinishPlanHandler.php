<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

final readonly class FinishPlanHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(FinishPlan $command): void
    {
        $now = $this->clock->now();
        $this->tx->run(function () use ($command, $now): void {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $plan->finish($now);
            $this->plans->save($plan);
        });
    }
}
