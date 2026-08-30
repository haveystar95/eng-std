<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface PlanRepository
{
    public function findById(PlanId $id): ?LearningPlan;

    /** Locked for the enclosing transaction — a status change is a decision two devices can race. */
    public function findForUpdate(PlanId $id): ?LearningPlan;

    /** The one plan in a HOLDING state ({@see PlanStatus::holdsTerms()}), or null. */
    public function findActiveFor(UserId $userId): ?LearningPlan;

    /**
     * Every plan whose hold on the pool still stands, for this learner.
     *
     * A list, not one row: `active` and `paused` both hold, the unique index only covers `active`,
     * and the strictness check has to see all of them.
     *
     * @return list<string> plan ids
     */
    public function holdingPlanIdsFor(UserId $userId): array;

    public function save(LearningPlan $plan): void;
}
