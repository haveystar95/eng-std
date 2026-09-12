<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** A scene and the photo it has. */
final readonly class SceneImageRef
{
    public function __construct(
        public PlanSceneId $sceneId,
        public Image $image,
    ) {}
}
