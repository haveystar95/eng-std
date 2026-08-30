<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Ask P1 for the skeleton, then run A1 over it. A re-run REPLACES both. */
final readonly class BuildPlanOutline
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
    ) {}
}
