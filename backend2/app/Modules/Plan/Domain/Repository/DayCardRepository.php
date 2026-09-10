<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;

interface DayCardRepository
{
    /** @return list<DayCard> in stage order, then position */
    public function forDay(PlanDayId $dayId): array;

    public function countForDay(PlanDayId $dayId): int;

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
}
