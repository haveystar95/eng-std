<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «Открыть» / «Продолжить» a day: the cards are dealt on the first open and re-read after. */
final readonly class OpenDay
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public UserId $actorId,
    ) {}
}
