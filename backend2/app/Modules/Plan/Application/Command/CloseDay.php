<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «День пройден»: metrics written, the words filed into the plan's collection, tomorrow unlocked. */
final readonly class CloseDay
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public UserId $actorId,
    ) {}
}
