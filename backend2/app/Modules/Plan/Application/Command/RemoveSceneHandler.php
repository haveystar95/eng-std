<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Shared\Domain\Service\TransactionManager;

final readonly class RemoveSceneHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanDispatcher $dispatcher,
        private TransactionManager $tx,
    ) {}

    public function __invoke(RemoveScene $command): void
    {
        $nextSceneId = $this->tx->run(function () use ($command): ?\App\Modules\Plan\Domain\ValueObject\PlanSceneId {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $plan->removeScene($command->sceneId);
            $this->plans->save($plan);

            // Day 1's lesson is written with the plan; a day 1 that just became a review has none to write, and the scene
            // day after it gets its lesson when day 1 closes (наряд GEN-3 §11).
            return $plan->currentSceneWithoutLesson()?->id();
        });

        if ($nextSceneId !== null) {
            $this->dispatcher->buildLesson($nextSceneId);
        }
    }
}
