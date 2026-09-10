<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The plan aggregate — plan row, scenes and days together. */
interface PlanRepository
{
    public function findById(PlanId $id): ?Plan;

    /** The plan for its owner, or null for anybody else's — the API never says «exists but not yours». */
    public function findOwned(PlanId $id, UserId $owner): ?Plan;

    /** Locked for the transaction, so two workers cannot both accept a blueprint. */
    public function findOwnedForUpdate(PlanId $id, UserId $owner): ?Plan;

    public function findByIdForUpdate(PlanId $id): ?Plan;

    /** The learner's live plan (active or overdue), if any. */
    public function findLiveFor(UserId $owner): ?Plan;

    public function save(Plan $plan): void;
}
