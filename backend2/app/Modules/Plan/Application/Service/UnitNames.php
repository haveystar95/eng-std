<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/** How a unit is named on a screen: a word's text, a phrase's text, an exchange's learner line. */
final class UnitNames
{
    public static function of(DayCard $card): ?string
    {
        $payload = $card->payload();

        return match ($card->unitKind()) {
            UnitKind::Word, UnitKind::Phrase => is_string($payload['text_target'] ?? null) ? $payload['text_target'] : null,
            UnitKind::Exchange => is_string($payload['expected'] ?? null)
                ? $payload['expected']
                : (is_string($payload['answer'] ?? null) ? $payload['answer'] : null),
        };
    }
}
