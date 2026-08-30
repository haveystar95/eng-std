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

    /**
     * This learner's plans, newest first — the План tab's archive (кадр 11).
     *
     * Drafts are excluded: a draft is a plan the learner started describing and walked away from,
     * and an archive that listed them would be a list of abandoned sentences rather than of
     * preparations that happened.
     *
     * @return list<LearningPlan>
     */
    public function listFor(UserId $userId, int $limit): array;

    public function save(LearningPlan $plan): void;
}
