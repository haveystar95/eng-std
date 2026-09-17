<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\CardKind;

/**
 * The judge rules only on the cards judged by meaning — `phrase_own_slot` and `speak_answer` (наряд SESSION-1a,
 * разд. 4). Every other kind is graded by the client without the network, and a model call on it would
 * be a paid verdict nobody reads — `speak_retell` among them since наряд BACK-TAILS-1 §1.1: it says the learner's own
 * line back, and coverage of that line is a thing the client counts itself.
 */
final class CardNotJudged extends PlanProblem
{
    public static function of(CardKind $kind): self
    {
        return new self("A {$kind->value} card is not judged by the server.", ['kind' => $kind->value]);
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'plan_card_not_judged';
    }

    public function problemTitle(): string
    {
        return 'This card is not judged by the server';
    }
}
