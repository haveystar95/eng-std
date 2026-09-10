<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The learner's plans for the list: live first, then newest first; deleted ones never. */
interface PlanListReader
{
    /** @return list<Plan> */
    public function allFor(UserId $owner): array;
}
