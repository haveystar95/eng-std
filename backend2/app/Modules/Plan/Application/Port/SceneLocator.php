<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** Which plan a scene belongs to — the one lookup a scene-addressed job needs before loading the aggregate. */
interface SceneLocator
{
    public function planIdOf(PlanSceneId $sceneId): ?PlanId;
}
