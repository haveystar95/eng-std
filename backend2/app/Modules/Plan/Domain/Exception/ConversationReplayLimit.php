<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use DateTimeImmutable;
use DateTimeZone;

/**
 * «ПОВТОРИТЬ РАЗГОВОР» — NOT TODAY ANY MORE (наряд BACK-TAILS-2 §7): the walked talk of this day was held again as many
 * times as one calendar day of the learner allows (`plan.conversation.replays_per_day`). Nothing is started and nothing is
 * bought; `retry_after_utc` is the learner's next midnight, when the replay opens again.
 */
final class ConversationReplayLimit extends PlanProblem
{
    public static function day(int $number, int $cap, DateTimeImmutable $retryAfter): self
    {
        $at = $retryAfter->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');

        return new self(
            "The talk of day {$number} was replayed {$cap} times today; again after {$at}.",
            ['day' => $number, 'replays_per_day' => $cap, 'retry_after_utc' => $at],
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_conversation_replay_limit';
    }

    public function problemTitle(): string
    {
        return 'The talk of this day cannot be replayed again today';
    }
}
