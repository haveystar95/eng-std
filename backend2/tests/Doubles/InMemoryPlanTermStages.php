<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;

/** Три факта про пару (план, термин), в памяти — {@see PlanTermStageRepository}. */
final class InMemoryPlanTermStages implements PlanTermStageRepository
{
    /** @var array<string, array<string, PlanTermStage>> plan id => term id => факт */
    public array $rows = [];

    public function forPlan(PlanId $planId): array
    {
        return $this->rows[$planId->value] ?? [];
    }

    public function save(PlanTermStage $stage): void
    {
        $this->rows[$stage->planId][$stage->termId] = $stage;
    }
}
