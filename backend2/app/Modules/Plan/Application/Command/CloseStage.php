<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Этап пройден» — every card of it answered; the server verifies rather than trusts. */
final readonly class CloseStage
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public Stage $stage,
        public UserId $actorId,
    ) {}
}
