<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Как прошло? Отметь, что сказал» — the abilities the learner used at the event. */
final readonly class RecordPlanFeedback
{
    /** @param list<int> $checkpointIndexes positions in the plan's checkpoint list */
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public array $checkpointIndexes,
    ) {}
}
