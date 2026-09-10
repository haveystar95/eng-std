<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * What a card is ABOUT: one word of the day, one phrase, or one exchange of the dialogue. A unit
 * is what fails twice and comes back tomorrow, and what «далось труднее всего» names.
 */
enum UnitKind: string
{
    case Word = 'word';
    case Phrase = 'phrase';
    case Exchange = 'exchange';
}
