<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** One square copy of a scene's photo, for the scene's owner. */
final readonly class GetSceneImage
{
    public function __construct(
        public PlanSceneId $sceneId,
        public SceneImageSize $size,
        public UserId $actorId,
    ) {}
}
