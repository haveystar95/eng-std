<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** Premium audio for the partner's line of every exchange of one scene. Queued, idempotent, best effort. */
final readonly class SpeakSceneLines
{
    public function __construct(public PlanSceneId $sceneId) {}
}
