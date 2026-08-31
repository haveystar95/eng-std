<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanSkillRecord;

/**
 * The plan's abilities, stored.
 *
 * One method, and that is the whole contract on purpose: the abilities of a plan are written as a
 * SET and never one at a time. They are P1's answer put through the scheduler, so a plan whose
 * schedule is recomputed has all of them rewritten together — a partial update would leave rows
 * from two different answers claiming to be the same plan, and nothing downstream could tell which
 * `day_index` to believe.
 */
interface PlanSkillRepository
{
    /** @param list<PlanSkillRecord> $skills in P1's order */
    public function replaceAll(PlanId $planId, array $skills): void;

    /** @return list<PlanSkillRecord> in P1's order */
    public function listForPlan(PlanId $planId): array;
}
