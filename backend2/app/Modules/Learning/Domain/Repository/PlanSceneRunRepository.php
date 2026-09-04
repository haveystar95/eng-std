<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanSceneRun;

/**
 * Журнал прогонов сцены — {@see PlanSceneRun}.
 *
 * Пишется по одному событию, читается планом целиком: и зрелость сцены на экране плана, и итог дня
 * спрашивают «что известно о прогонах», а не «что было в пятницу».
 */
interface PlanSceneRunRepository
{
    public function add(PlanSceneRun $run): void;

    /**
     * Все прогоны плана, старые первыми.
     *
     * @return list<PlanSceneRun>
     */
    public function forPlan(PlanId $planId): array;
}
