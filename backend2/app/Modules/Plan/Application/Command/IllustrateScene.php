<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** The photos of one scene's day — its own and every word's — found together; then the day is ready (DAY-UI-3). */
final readonly class IllustrateScene
{
    public function __construct(public PlanSceneId $sceneId) {}
}
