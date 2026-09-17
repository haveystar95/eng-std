<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * The day's lesson is still being written (наряд GEN-3 §11): the day is next in line, but its lesson was asked for when the
 * day before it closed and has not come back yet. Not `locked` (nothing the learner does opens it) and not `failed` (nothing
 * to retry): wait and ask again.
 */
final class PlanDayBuilding extends PlanProblem
{
    public static function day(int $number, string $lessonStatus): self
    {
        return new self("Day {$number} is being built.", ['day' => $number, 'lesson_status' => $lessonStatus]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_day_building';
    }

    public function problemTitle(): string
    {
        return 'This day\'s lesson is still being built';
    }
}
