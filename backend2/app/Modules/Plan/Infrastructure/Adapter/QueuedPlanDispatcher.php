<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Job\AttachPlanImagesJob;
use App\Modules\Plan\Infrastructure\Job\BuildLessonJob;
use App\Modules\Plan\Infrastructure\Job\BuildPlanJob;
use App\Modules\Plan\Infrastructure\Job\IllustrateSceneJob;
use App\Modules\Plan\Infrastructure\Job\VoiceRescueKitJob;
use App\Modules\Plan\Infrastructure\Job\VoiceSceneJob;
use Illuminate\Support\Facades\Log;

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

    public function illustrateScene(PlanSceneId $sceneId): void
    {
        IllustrateSceneJob::dispatch($sceneId->value);
    }

    public function voiceScene(PlanSceneId $sceneId): void
    {
        // A database voiced only by name (the e2e stand): a new day is not voiced on its own there (TTS-2).
        if (VoiceDatabase::namedPlansOnly()) {
            Log::info('voice not queued: this database is voiced only by plan:speak-backfill --plan', ['scene_id' => $sceneId->value]);

            return;
        }
        VoiceSceneJob::dispatch($sceneId->value);
    }

    public function voiceRescueKit(PlanId $planId): void
    {
        // Voiced only by name (the e2e stand): the kit is not bought on its own there either (TTS-2).
        if (VoiceDatabase::namedPlansOnly()) {
            Log::info('rescue kit voice not queued: this database is voiced only by name', ['plan_id' => $planId->value]);

            return;
        }
        VoiceRescueKitJob::dispatch($planId->value);
    }
}
