<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Не собрался — ещё раз»: the learner's explicit retry of a failed (or stuck) plan build. */
final readonly class RetryPlanBuild
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
    ) {}
}
