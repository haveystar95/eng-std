<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** The queued lesson call for one scene. */
final readonly class BuildLesson
{
    public function __construct(public PlanSceneId $sceneId) {}
}
