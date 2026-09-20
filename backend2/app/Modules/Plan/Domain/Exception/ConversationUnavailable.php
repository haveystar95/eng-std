<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * The role did not answer — the model was silent, timed out or answered off the schema. The ribbon
 * stays exactly where it was and the learner is offered «Повторить» (кадр 37-10): a turn that was
 * not answered is not written, so retrying repeats the move rather than doubling it.
 */
final class ConversationUnavailable extends PlanProblem
{
    public static function model(string $why): self
    {
        return new self("The conversation agent did not answer: {$why}.");
    }

    public function problemStatus(): int
    {
        return 503;
    }

    public function problemCode(): string
    {
        return 'plan_conversation_unavailable';
    }

    public function problemTitle(): string
    {
        return 'The partner did not answer';
    }
}
