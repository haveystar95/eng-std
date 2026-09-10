<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\DayStatus;

/** An answer, a stage close or a day close on a day that is not in progress. */
final class PlanDayNotOpen extends PlanProblem
{
    public static function day(int $number, DayStatus $status): self
    {
        return new self("Day {$number} is {$status->value}, not in progress.", ['day' => $number, 'status' => $status->value]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_day_not_open';
    }

    public function problemTitle(): string
    {
        return 'This day is not in progress';
    }
}
