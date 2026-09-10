<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Plan\Application\Command\BuildLesson;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Eloquent\PlanSceneModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** The lesson call for one scene — one try, for the reason {@see BuildPlanJob} gives. */
final class BuildLessonJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(private readonly string $sceneId)
    {
        $this->timeout = 2 * (int) config('plan.model.lesson_timeout', 90) + 30;
    }

    public function handle(BuildLessonHandler $handler): void
    {
        $handler(new BuildLesson(PlanSceneId::fromString($this->sceneId)));
    }

    public function failed(Throwable $e): void
    {
        Log::error('BuildLessonJob failed', ['scene_id' => $this->sceneId, 'error' => $e->getMessage()]);
        PlanSceneModel::query()->whereKey($this->sceneId)->where('lesson_status', 'building')
            ->update(['lesson_status' => 'failed', 'fail_reason' => 'Сборка урока упала: '.mb_substr($e->getMessage(), 0, 500), 'updated_at' => now()]);
    }
}
