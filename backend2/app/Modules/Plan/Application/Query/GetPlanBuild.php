<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The poll after «создать»: is the plan built yet. */
final readonly class GetPlanBuild
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
    ) {}
}
