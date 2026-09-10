<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/** A reschedule cannot cut days the learner has already opened or closed. */
final class PlanTooShort extends PlanProblem
{
    public static function minimum(int $minDays): self
    {
        return new self("The plan cannot have fewer than {$minDays} days now.", ['min_days' => $minDays]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_too_short';
    }

    public function problemTitle(): string
    {
        return 'The plan cannot be shortened below the days already walked';
    }
}
