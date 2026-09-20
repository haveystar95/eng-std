<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/** No such talk for this learner — someone else's id reads the same as one that never existed. */
final class ConversationNotFound extends PlanProblem
{
    public static function id(string $id): self
    {
        return new self("Conversation {$id} not found.", ['conversation_id' => $id]);
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_conversation_not_found';
    }

    public function problemTitle(): string
    {
        return 'Conversation not found';
    }
}
