<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Pause, abandon or complete — one command, because the three differ only in the state they land
 * in and in whether the plan's hold on the pool survives.
 */
final readonly class EndPlan
{
    public const PAUSE = 'pause';
    public const ABANDON = 'abandon';
    public const COMPLETE = 'complete';

    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public string $action,
    ) {}
}
