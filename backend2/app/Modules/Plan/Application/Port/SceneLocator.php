<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\SceneImageRef;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Scene lookups that need no aggregate: which plan a scene belongs to, and its photo. */
interface SceneLocator
{
    public function planIdOf(PlanSceneId $sceneId): ?PlanId;

    /** The scene's photo when the scene is this learner's and has one; null otherwise — one row by primary key. */
    public function ownedImage(PlanSceneId $sceneId, UserId $owner): ?Image;

    /**
     * Every scene with a photo — of one plan, or of all plans — for the image backfill.
     *
     * @return list<SceneImageRef>
     */
    public function scenesWithImages(?PlanId $planId): array;
}
