<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DomainException;

/**
 * «Убрать из изучения» on a word an active plan is standing on.
 *
 * The refusal that makes a plan a MECHANISM rather than a suggestion. Every other word in the app
 * can be paused at any moment and nothing breaks; that promise is real and this is the one
 * exception to it, so it has to be explicit and it has to say what to do instead.
 *
 * The reasoning: day 3 promised «понять назначение и повторить своими словами», the conversation on
 * day 3 checks exactly that, and the words are what the ability is made of. Removing one leaves a
 * plan that still makes the promise and can no longer keep it — and the learner would find out at
 * the conversation, which is the worst possible moment. The way out is not to remove the word, it
 * is to pause or abandon the PLAN, which releases every word it held in one act.
 *
 * `meta.plan_ids` carries the plans doing the holding, so the client can offer that button rather
 * than an error message with no next step.
 */
final class PlanHeldTerm extends DomainException implements ProblemDetails
{
    /** @param list<string> $planIds */
    private function __construct(
        private readonly string $termId,
        private readonly array $planIds,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** @param list<string> $planIds */
    public static function make(TermId $termId, array $planIds): self
    {
        return new self(
            $termId->value,
            $planIds,
            'Это слово держит активный план: день, который его вводит, обещает умение, а разговор '
            . 'в конце дня это умение проверяет. Убрать слово нельзя — можно поставить план на '
            . 'паузу или отказаться от него, и тогда все его слова станут обычными.',
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_held_term';
    }

    public function problemTitle(): string
    {
        return 'The term is held by a running plan';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['term_id' => $this->termId, 'plan_ids' => $this->planIds];
    }
}
