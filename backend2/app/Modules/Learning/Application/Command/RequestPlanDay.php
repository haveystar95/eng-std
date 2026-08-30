<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «Собери мне день n» — the learner looking ahead.
 *
 * The day exists in the skeleton; refusing to build it would be pretending it does not. What this
 * path may not do is run away: {@see \App\Modules\Learning\Domain\Service\PlanGenerationPolicy}
 * caps how many days may stand ahead of the one the learner is on, and only one may be generating
 * at a time.
 */
final readonly class RequestPlanDay
{
    public function __construct(
        public UserId $actorId,
        public string $planId,
        public int $dayIndex,
    ) {}
}
