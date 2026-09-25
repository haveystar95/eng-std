<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\MissingImageCounts;
use App\Modules\Plan\Application\Dto\SceneImageRef;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

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
     * Whose voices some scenes are said in — the partner's stored gender and voice (наряд FIX-4c §1), and the learner
     * whose plan it is — one query by primary key, for a reader that holds cards and no aggregate (DAY-UI-3; наряд FIX-3
     * §1: the learner's voice is the learner's own, {@see \App\Modules\Plan\Application\Service\VoiceCasts}). A scene
     * not found is simply absent.
     *
     * @param  list<string>  $sceneIds
     * @return array<string, array{partner: VoiceGender|null, voice: string|null, learner: UserId}>
     */
    public function voicesOf(array $sceneIds): array;
}
