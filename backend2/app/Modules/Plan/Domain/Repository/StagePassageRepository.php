<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StagePassage;

/**
 * THE JOURNAL OF WALKED STAGES (наряд CONV-2, п. 2) — APPEND-ONLY, and the implementation is what keeps it so: there is
 * no update and no delete, only {@see record()}, which writes a day's stage once and leaves an existing row alone.
 */
interface StagePassageRepository
{
    /** Writes the passage unless the day's stage already has one. True when this call wrote it. */
    public function record(StagePassage $passage): bool;

    /** The passage of one day's stage, if it was walked. */
    public function of(PlanDayId $dayId, Stage $stage): ?StagePassage;

    /**
     * The passages of one stage for a number of days — one query for a route or a plan.
     *
     * @param  list<PlanDayId>  $dayIds
     * @return array<string, StagePassage> by day id
     */
    public function ofDays(array $dayIds, Stage $stage): array;
}
