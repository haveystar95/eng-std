<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Урок не собрался — ещё раз»: the learner's explicit retry of a failed (or stuck) lesson. */
final readonly class RetryLesson
{
    public function __construct(
        public PlanId $planId,
        public PlanSceneId $sceneId,
        public UserId $actorId,
    ) {}
}
