<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * What a card is ABOUT: one word of the day, one phrase, one exchange of the dialogue — or the day's
 * listening as a whole (наряд SESSION-1a, D-05). A word, a phrase or an exchange is what fails twice and
 * comes back tomorrow, and what «далось труднее всего» names; the listening of a day is not a unit anyone
 * can return — the next day has another dialogue.
 */
enum UnitKind: string
{
    case Word = 'word';
    case Phrase = 'phrase';
    case Exchange = 'exchange';
    case Day = 'day';

    /** Whether a unit failed twice comes back on the next content day — every unit but the day's listening. */
    public function returns(): bool
    {
        return $this !== self::Day;
    }
}
