<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Pause, abandon or complete — one command, because the three differ only in the state they land
 * in and in whether the plan's hold on the pool survives.
 *
 * The action is a {@see PlanEnding} and not a string, and that is the whole of what v0.2.1 changed
 * here: `'abandoned'` used to be a compilable way of saying `complete`. The enum's docblock has the
 * story.
 */
final readonly class EndPlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public PlanEnding $action,
    ) {}
}
