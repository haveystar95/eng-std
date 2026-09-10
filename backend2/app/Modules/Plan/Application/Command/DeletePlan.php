<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Удалить план» from the menu. The plan's collection, if it has one, stays — its words are the learner's. */
final readonly class DeletePlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
    ) {}
}
