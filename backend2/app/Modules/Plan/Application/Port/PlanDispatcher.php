<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * Puts the asynchronous work on the queue. Nothing in Application waits on a model: every call
 * is a job the client polls, and every job is idempotent — a second dispatch of the same thing
 * finds the work claimed and stops.
 */
interface PlanDispatcher
{
    /** Ask the model for the plan; `$scenesToAdd` > 0 means an extension of an existing plan. */
    public function buildPlan(PlanId $planId, int $scenesToAdd = 0): void;

    public function buildLesson(PlanSceneId $sceneId): void;

    /** Photos for the plan's cover, its scenes and its terms — best effort, never blocking. */
    public function attachImages(PlanId $planId): void;

    /** Audio for the partner's lines of one scene — best effort, never blocking. */
    public function speakScene(PlanSceneId $sceneId): void;
}
