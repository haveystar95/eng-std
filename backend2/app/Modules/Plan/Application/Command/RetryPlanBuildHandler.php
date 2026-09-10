<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Exception\PlanNotInState;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

final readonly class RetryPlanBuildHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanDispatcher $dispatcher,
        private PlanConfig $config,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(RetryPlanBuild $command): void
    {
        $now = $this->clock->now();
        $this->tx->run(function () use ($command, $now): void {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $retryable = in_array($plan->status(), [PlanStatus::Failed, PlanStatus::Unclear], true)
                || $plan->isBuildStale($now, $this->config->buildStaleSeconds);
            if (! $retryable) {
                throw PlanNotInState::for('retry', $plan->status(), [PlanStatus::Failed, PlanStatus::Unclear]);
            }
            $plan->beginBuild($now);
            $this->plans->save($plan);
        });

        $this->dispatcher->buildPlan($command->planId);
    }
}
