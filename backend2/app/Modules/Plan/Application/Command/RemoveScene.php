<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** A scene taken out of the preview; its day becomes a review day. */
final readonly class RemoveScene
{
    public function __construct(
        public PlanId $planId,
        public PlanSceneId $sceneId,
        public UserId $actorId,
    ) {}
}
