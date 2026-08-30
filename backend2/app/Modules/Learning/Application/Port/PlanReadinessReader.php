<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * What share of a plan's material this learner has actually acquired, 0…1.
 *
 * A port because the answer moves: today it is «graduated off the recognition ladder», in 1b it
 * becomes «на ступени C», and the plan should not have to change when it does. The formula that
 * uses this number lives in {@see \App\Modules\Learning\Application\Query\GetPlanHandler}; what
 * counts as acquired lives behind this seam.
 */
interface PlanReadinessReader
{
    /** @param list<string> $collectionIds the plan's day collections */
    public function acquiredShare(UserId $userId, array $collectionIds): float;
}
