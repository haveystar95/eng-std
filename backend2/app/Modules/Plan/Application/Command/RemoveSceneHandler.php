<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\DayType;
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

            // Day 1's lesson is written before the start; if day 1 just became a review, the first
            // scene day moves and its lesson is what should be waiting.
            $first = $plan->day(1);
            $day = $first->type() === DayType::Scene ? $first : $plan->nextSceneDayAfter(1);
            $scene = $day === null ? null : $plan->sceneOf($day);

            return $scene !== null && $scene->needsLesson() ? $scene->id() : null;
        });

        if ($nextSceneId !== null) {
            $this->dispatcher->buildLesson($nextSceneId);
        }
    }
}
