<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** One live plan per learner: finish or delete the running one before starting another. */
final class PlanAlreadyActive extends PlanProblem
{
    public static function holding(PlanId $active): self
    {
        return new self("Another plan is active: {$active->value}", ['active_plan_id' => $active->value]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_already_active';
    }

    public function problemTitle(): string
    {
        return 'Another plan is already active';
    }
}
