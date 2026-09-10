<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** Not this learner's plan, or no such plan — the same answer for both, like every other module. */
final class PlanNotFound extends PlanProblem
{
    public static function withId(PlanId $id): self
    {
        return new self("Plan not found: {$id->value}", ['plan_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_not_found';
    }

    public function problemTitle(): string
    {
        return 'Plan not found';
    }
}
