<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\DayCardId;

/** A card takes one answer; a replay of the same answer is refused, never re-counted. */
final class CardAlreadyAnswered extends PlanProblem
{
    public static function withId(DayCardId $id): self
    {
        return new self("Card already answered: {$id->value}", ['card_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_card_answered';
    }

    public function problemTitle(): string
    {
        return 'Card already answered';
    }
}
