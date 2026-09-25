<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * A talk asked of a day that has no talk in it: its sixth stage is skipped (наряд ACC-1 §3) — the day was dealt with
 * nothing for the role to lead with (no scene of it has a written lesson), or on the five stages of before наряд
 * CONV-1 — or, at the moment of asking, none of its scenes has a written lesson.
 */
final class ConversationNotInDay extends PlanProblem
{
    public static function day(int $number, string $why): self
    {
        return new self("Day {$number} has no conversation: {$why}.", ['day' => $number, 'reason' => $why]);
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'plan_conversation_not_in_day';
    }

    public function problemTitle(): string
    {
        return 'This day has no conversation';
    }
}
