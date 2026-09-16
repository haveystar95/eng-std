<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;

interface DayCardRepository
{
    /** @return list<DayCard> in stage order, then position */
    public function forDay(PlanDayId $dayId): array;

    public function countForDay(PlanDayId $dayId): int;

    /**
     * Every dealt day of the plan counted per stage, in one grouped query — what the route reads
     * its stages from. A day with no card is not in the result.
     *
     * @return array<string, array<string, array{total: int, answered: int}>> day id → stage → counts
     */
    public function stageTallies(PlanId $planId): array;

    public function find(DayCardId $id): ?DayCard;

    public function findForUpdate(DayCardId $id): ?DayCard;

    /** @param list<DayCard> $cards a whole day's cards, written in one statement */
    public function insertAll(array $cards): void;

    public function save(DayCard $card): void;

    /**
     * The cards of a day that failed twice — the units that return tomorrow.
     *
     * @return list<DayCard>
     */
    public function returningFrom(PlanDayId $dayId): array;

    /**
     * The cards already dealt back from these days — returns (`source = returned`) whose `source_day_id` is one of
     * them, on any day. What «ещё никуда не возвращались» is read from.
     *
     * @param  list<PlanDayId>  $sourceDays
     * @return list<DayCard>
     */
    public function returnedFrom(array $sourceDays): array;
}
