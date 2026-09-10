<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The day's full card list — read after «открыть», and again on «продолжить». */
final readonly class GetDayCards
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public UserId $actorId,
    ) {}
}
