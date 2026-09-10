<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The whole plan — tab and preview alike. */
final readonly class GetPlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
    ) {}
}
