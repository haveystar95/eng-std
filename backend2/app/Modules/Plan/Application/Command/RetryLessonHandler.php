<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Exception\LessonNotReady;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

final readonly class RetryLessonHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanDispatcher $dispatcher,
        private PlanConfig $config,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(RetryLesson $command): void
    {
        $now = $this->clock->now();
        $this->tx->run(function () use ($command, $now): void {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $scene = $plan->scene($command->sceneId);
            $retryable = $scene->lessonStatus() === LessonStatus::Failed
                || $scene->isBuildStale($now, $this->config->buildStaleSeconds);
            if (! $retryable) {
                throw LessonNotReady::scene($scene->id(), $scene->lessonStatus(), 'Урок не в состоянии, из которого его можно перезапросить.');
            }
            $scene->resetLesson();
            $this->plans->save($plan);
        });

        $this->dispatcher->buildLesson($command->sceneId);
    }
}
