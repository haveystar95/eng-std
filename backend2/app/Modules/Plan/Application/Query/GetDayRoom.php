<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class GetDayRoom
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public UserId $actorId,
    ) {}
}
