<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Job\AttachPlanImagesJob;
use App\Modules\Plan\Infrastructure\Job\BuildLessonJob;
use App\Modules\Plan\Infrastructure\Job\BuildPlanJob;
use App\Modules\Plan\Infrastructure\Job\SpeakSceneLinesJob;

final class QueuedPlanDispatcher implements PlanDispatcher
{
    public function buildPlan(PlanId $planId, int $scenesToAdd = 0): void
    {
        BuildPlanJob::dispatch($planId->value, $scenesToAdd);
    }

    public function buildLesson(PlanSceneId $sceneId): void
    {
        BuildLessonJob::dispatch($sceneId->value);
    }

    public function attachImages(PlanId $planId): void
    {
        AttachPlanImagesJob::dispatch($planId->value);
    }

    public function speakScene(PlanSceneId $sceneId): void
    {
        SpeakSceneLinesJob::dispatch($sceneId->value);
    }
}
