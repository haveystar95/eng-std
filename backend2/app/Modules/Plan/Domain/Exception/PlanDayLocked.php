<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/** The day is not available yet: the previous one is still open, or the calendar has not turned. */
final class PlanDayLocked extends PlanProblem
{
    public static function behindDay(int $number, int $blockedBy): self
    {
        return new self("Day {$number} is locked by day {$blockedBy}.", ['day' => $number, 'blocked_by_day' => $blockedBy]);
    }

    public static function untilDate(int $number, string $opensOn): self
    {
        return new self("Day {$number} opens on {$opensOn}.", ['day' => $number, 'opens_on' => $opensOn]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_day_locked';
    }

    public function problemTitle(): string
    {
        return 'This day is locked';
    }
}
