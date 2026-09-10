<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\DayCardId;

final class CardNotFound extends PlanProblem
{
    public static function withId(DayCardId $id): self
    {
        return new self("Card not found: {$id->value}", ['card_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_card_not_found';
    }

    public function problemTitle(): string
    {
        return 'Card not found';
    }
}
