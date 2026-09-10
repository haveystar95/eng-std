<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\TransactionManager;

final readonly class DeletePlanHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private TransactionManager $tx,
    ) {}

    public function __invoke(DeletePlan $command): void
    {
        $this->tx->run(function () use ($command): void {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $plan->delete();
            $this->plans->save($plan);
        });
    }
}
