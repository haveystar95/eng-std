<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\MissingImageCounts;
use App\Modules\Plan\Application\Dto\SceneImageRef;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
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

    /**
     * The plans that are not deleted and still have a scene, a word or a chunk without a photo — or
     * just the one plan asked for — for the image backfill (DAY-UI-2).
     *
     * @return list<PlanId>
     */
    public function plansMissingImages(?PlanId $planId): array;

    /** «Было пусто / стало»: scenes and words/chunks without a photo, of the plans that are not deleted. */
    public function missingImageCounts(?PlanId $planId): MissingImageCounts;

    /**
     * The two voices of some scenes — one query by primary key, for a reader that holds cards and no
     * aggregate (DAY-UI-3). A scene not found is simply absent.
     *
     * @param  list<string>  $sceneIds
     * @return array<string, VoiceCast>
     */
    public function voiceCastsOf(array $sceneIds): array;
}
