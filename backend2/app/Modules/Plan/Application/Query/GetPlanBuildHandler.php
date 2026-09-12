<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\PlanBuildView;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class GetPlanBuildHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanViews $views,
        private PlanConfig $config,
        private Clock $clock,
    ) {}

    public function __invoke(GetPlanBuild $query): PlanBuildView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);
        $call = $plan->planCall();

        // Four values and no fifth (docs/plan-api.md): a build that started and never came back
        // is reported as `failed`, so the client can ask for a retry instead of polling a spinner
        // forever — and a plan that is BUILT is `ready`, whatever it has become since. «Начать» is
        // legal while day 1's lesson is still being written, so a poll that outlives the press
        // would otherwise answer `active`, a word this endpoint does not have.
        $status = match (true) {
            $plan->isBuildStale($this->clock->now(), $this->config->buildStaleSeconds) => PlanStatus::Failed,
            $plan->status()->isBuilt() => PlanStatus::Ready,
            default => $plan->status(),
        };

        return new PlanBuildView(
            id: $plan->id()->value,
            status: $status->value,
            unclearReason: $plan->unclearReason(),
            failReason: $status === PlanStatus::Failed ? ($plan->failReason() ?? 'Сборка не завершилась вовремя.') : null,
            scenesCount: count($plan->scenes()),
            costUsd: $call?->costUsd,
            latencyMs: $call?->latencyMs,
            attempts: $call?->attempts,
            versions: $this->views->versions(),
        );
    }
}
