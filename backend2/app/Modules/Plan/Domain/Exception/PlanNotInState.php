<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanStatus;

/** The action needs the plan in another state — «start» on a plan still building, «finish» on a draft. */
final class PlanNotInState extends PlanProblem
{
    /** @param list<PlanStatus> $wanted */
    public static function for(string $action, PlanStatus $actual, array $wanted): self
    {
        $names = implode('|', array_map(static fn (PlanStatus $s): string => $s->value, $wanted));

        return new self(
            "«{$action}» needs the plan {$names}, it is {$actual->value}.",
            ['status' => $actual->value, 'wanted' => array_map(static fn (PlanStatus $s): string => $s->value, $wanted)],
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_state';
    }

    public function problemTitle(): string
    {
        return 'Plan is not in the state this action needs';
    }
}
