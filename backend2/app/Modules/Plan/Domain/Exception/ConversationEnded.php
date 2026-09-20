<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\ConversationId;

/**
 * A move sent to a talk that is over. The talk is not reopened — «Ещё раз» starts a new one
 * (наряд CONV-1), so what was said stays said.
 */
final class ConversationEnded extends PlanProblem
{
    public static function talk(ConversationId $id): self
    {
        return new self("Conversation {$id->value} has ended.", ['conversation_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_conversation_ended';
    }

    public function problemTitle(): string
    {
        return 'This conversation has ended';
    }
}
