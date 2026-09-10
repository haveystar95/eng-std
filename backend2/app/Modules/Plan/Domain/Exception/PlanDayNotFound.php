<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

final class PlanDayNotFound extends PlanProblem
{
    public static function number(int $number): self
    {
        return new self("Day {$number} does not exist in this plan.", ['day' => $number]);
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_day_not_found';
    }

    public function problemTitle(): string
    {
        return 'Day not found';
    }
}
