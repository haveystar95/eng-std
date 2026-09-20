<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\ConversationState;

/**
 * A move sent while the role is still answering the previous one. Two `POST …/turn` in flight at
 * once would buy two replies to one line and put them both in the ribbon; the second is refused.
 */
final class ConversationNotYourTurn extends PlanProblem
{
    public static function state(ConversationState $state): self
    {
        return new self("The conversation is {$state->value}, not waiting for the learner.", ['state' => $state->value]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_conversation_not_your_turn';
    }

    public function problemTitle(): string
    {
        return 'It is not the learner\'s turn';
    }
}
