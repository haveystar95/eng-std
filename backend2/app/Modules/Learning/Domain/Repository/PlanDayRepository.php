<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanId;

interface PlanDayRepository
{
    /** @return list<PlanDay> in day order */
    public function listForPlan(PlanId $planId): array;

    public function findByIndex(PlanId $planId, int $dayIndex): ?PlanDay;

    /** Locked: {@see PlanDay::claim()} is only idempotent if the read that precedes it is. */
    public function findByIndexForUpdate(PlanId $planId, int $dayIndex): ?PlanDay;

    public function findById(PlanDayId $id): ?PlanDay;

    public function save(PlanDay $day): void;

    /**
     * Replace the whole day list of a DRAFT plan.
     *
     * Whole-list, because A1 recomputes the days as a set: a day that moved from index 3 to index 2
     * is not an update of day 3. Only ever reached on a draft, where no day owns a collection yet
     * and nothing can be orphaned.
     *
     * @param  list<PlanDay>  $days
     */
    public function replaceAll(PlanId $planId, array $days): void;
}
