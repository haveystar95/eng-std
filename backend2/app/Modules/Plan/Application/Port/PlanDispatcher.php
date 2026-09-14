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

    /** Photos for the plan's cover and its scenes — the route's pictures, before any lesson is written. */
    public function attachImages(PlanId $planId): void;

    /** The day's pictures — its scene's and every word's — right after its lesson; the day is ready after them (DAY-UI-3). */
    public function illustrateScene(PlanSceneId $sceneId): void;

    /** The server's voice for everything a scene says out loud — never holding the day back (DAY-UI-3). */
    public function voiceScene(PlanSceneId $sceneId): void;
}
