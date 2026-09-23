<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Plan\Application\Inspection\PlanInspection;

/** Asks the plan's owner for the learner's plans, each as its row: code, title, status, event date, progress, money. */
final readonly class ListLearnerPlansHandler
{
    public function __construct(private PlanInspection $plans) {}

    /** @return list<array<string, mixed>> */
    public function __invoke(ListLearnerPlans $query): array
    {
        return $this->plans->plansOf($query->userId);
    }
}
