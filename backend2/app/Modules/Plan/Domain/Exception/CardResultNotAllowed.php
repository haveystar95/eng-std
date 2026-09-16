<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;

/**
 * A result the card's kind cannot have (наряд SESSION-1a, D-31): a judged card is passed only by the judge, so the
 * client may only give it up; a walkthrough is walked or skipped, never right or wrong; a spoken card never fails.
 * Refused rather than stored — a `passed` the client wrote on a judged card would be a pass nobody judged.
 */
final class CardResultNotAllowed extends PlanProblem
{
    public static function of(CardKind $kind, CardResult $result): self
    {
        return new self(
            "A {$kind->value} card cannot be answered «{$result->value}».",
            ['kind' => $kind->value, 'result' => $result->value],
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'plan_card_result_not_allowed';
    }

    public function problemTitle(): string
    {
        return 'This result is not allowed for this card';
    }
}
