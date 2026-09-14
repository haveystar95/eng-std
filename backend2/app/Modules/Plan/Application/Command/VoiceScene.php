<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** The server's voice for everything one scene says out loud (DAY-UI-3). Queued, idempotent, never blocking the day. */
final readonly class VoiceScene
{
    public function __construct(public PlanSceneId $sceneId) {}
}
