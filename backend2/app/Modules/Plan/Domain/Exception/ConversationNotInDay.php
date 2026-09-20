<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * A talk asked of a day that has no talk in it. Days opened before наряд CONV-1 keep the five
 * stages they were dealt with (`plan_days.has_conversation`), and a day whose scenes have no
 * written lesson has nothing for the role to lead with.
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
