<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Plan\Application\Command\BuildLesson;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Eloquent\PlanSceneModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The day of one scene — one try, for the reason {@see BuildPlanJob} gives; a job that ran out of time fails, and the lesson
 * waits for the learner's retry (наряд GEN-3).
 *
 * Its timeout covers every model call the build may make, each waited for as long as the plan's config says, and a minute
 * for the writes (наряд GEN-4): each of the two stages with its one repeat, the repairs of each stage's cards (four since
 * наряд GEN-4c), the seam judge and its second read of what the repairs changed. Anything shorter kills a healthy build
 * between two paid calls; the queue's `retry_after` and the stale window of a build (`plan.build_stale_seconds`) stay above it.
 */
final class BuildLessonJob implements ShouldQueue
{
    use Queueable;

    /** Seconds past the calls for the claim, the writes and the queue's own bookkeeping. */
    public const MARGIN_SECONDS = 60;

    public int $tries = 1;

    public int $timeout;

    public function __construct(private readonly string $sceneId)
    {
        $this->timeout = self::timeoutSeconds((int) config('plan.model.lesson_timeout'));
    }

    /** The job's timeout for a lesson whose every call waits `$callTimeout` seconds for its answer. */
    public static function timeoutSeconds(int $callTimeout): int
    {
        $calls = 2 * (LessonBuildService::STAGE_ATTEMPTS + LessonBuildService::REPAIR_CARDS) + 2;

        return $calls * $callTimeout + self::MARGIN_SECONDS;
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
