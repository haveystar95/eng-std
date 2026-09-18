<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\CardKind;

/**
 * The client named a chosen option on a card that has nothing to choose (наряд BACK-TAILS-1, доработка §1): a kind
 * that carries no check at all, or a `dialogue_ask` dealt without one (its three check keys come together or not at
 * all), or an option id that is not among the card's own.
 *
 * Refused before the card is touched, like a result the kind cannot have: a choice whose option nobody can name would
 * be a lapse decided on nothing — a copy dealt and an exchange returned for an answer the server cannot read.
 */
final class CardChoiceNotAllowed extends PlanProblem
{
    public static function of(CardKind $kind, string $choice): self
    {
        return new self(
            "A {$kind->value} card cannot be answered with the choice «{$choice}».",
            ['kind' => $kind->value, 'choice' => $choice],
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'plan_card_choice_not_allowed';
    }

    public function problemTitle(): string
    {
        return 'This card has no such option to choose';
    }
}
