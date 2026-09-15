<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** Delete the lines of a scene filed under a voice their speaker no longer has (TTS-2) — before they are bought anew. */
final readonly class DropUnreadVoice
{
    public function __construct(public PlanSceneId $sceneId) {}
}
