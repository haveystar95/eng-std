<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;

/**
 * THE PLAN'S JOURNAL — append and read, nothing else. There is deliberately no update and no
 * delete here: the only ways a line leaves `plan_events` are the account eraser and the FK
 * cascade of a hard-deleted plan (a plan the learner deletes is soft-deleted and keeps its journal).
 */
interface PlanEventRepository
{
    /**
     * Write the line. False when the schema already holds it — a once-per-plan kind that is there
     * already, or a day passed twice — so a repeated tick or a retried job cannot double a fact.
     */
    public function append(PlanEvent $event): bool;

    public function has(PlanId $planId, PlanEventKind $kind): bool;

    /** @return list<PlanEvent> oldest first */
    public function forPlan(PlanId $planId): array;
}
