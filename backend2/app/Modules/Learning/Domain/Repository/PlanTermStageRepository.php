<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;

/**
 * Три факта про пару (план, термин), которых нет в журнале ответов — {@see PlanTermStage}.
 *
 * Читается ЦЕЛЫМ ПЛАНОМ, а не по карточке: и посадка, и экран плана, и итог прогона спрашивают
 * «где стоит каждая реплика этого плана», и запрос по одной карточке в цикле превратил бы один
 * запрос в три десятка. Пишется по одной паре — ответ всегда про один ход.
 */
interface PlanTermStageRepository
{
    /** @return array<string, PlanTermStage> term id => факт; отсутствующая пара — значения по умолчанию */
    public function forPlan(PlanId $planId): array;

    /** Записать факт пары; строки может ещё не быть. */
    public function save(PlanTermStage $stage): void;
}
