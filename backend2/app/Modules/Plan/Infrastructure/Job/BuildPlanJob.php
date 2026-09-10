<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Plan\Application\Command\BuildPlan;
use App\Modules\Plan\Application\Command\BuildPlanHandler;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Infrastructure\Eloquent\PlanModel;
use App\Modules\Plan\Infrastructure\Eloquent\PlanSceneModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The plan call. ONE try: the handler already retries the model once on a refused answer, and a
 * queue retry on top would be a third paid call for the same plan. A job that dies outright leaves
 * the plan `building`; the stale-build window turns that into `failed` for the client.
 */
final class BuildPlanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        private readonly string $planId,
        private readonly int $scenesToAdd = 0,
    ) {
        $this->timeout = 2 * (int) config('plan.model.plan_timeout', 90) + 30;
    }

    public function handle(BuildPlanHandler $handler): void
    {
        $handler(new BuildPlan(PlanId::fromString($this->planId), $this->scenesToAdd));
    }

    public function failed(Throwable $e): void
    {
        Log::error('BuildPlanJob failed', ['plan_id' => $this->planId, 'scenes_to_add' => $this->scenesToAdd, 'error' => $e->getMessage()]);
        if ($this->scenesToAdd > 0) {
            return;
        }
        // The plan row is a fact the client polls; a worker that died mid-call must not leave it
        // spinning until the stale window — say what happened, in the row the client reads.
        PlanModel::query()->whereKey($this->planId)->where('status', 'building')
            ->update(['status' => 'failed', 'fail_reason' => 'Сборка плана упала: '.mb_substr($e->getMessage(), 0, 500), 'updated_at' => now()]);
        PlanSceneModel::query()->where('plan_id', $this->planId)->where('lesson_status', 'building')->update(['lesson_status' => 'failed']);
    }
}
