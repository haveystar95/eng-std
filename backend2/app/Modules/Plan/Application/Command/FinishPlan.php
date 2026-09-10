<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Завершить»: the plan is done, whether the event has passed or not. */
final readonly class FinishPlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
    ) {}
}
