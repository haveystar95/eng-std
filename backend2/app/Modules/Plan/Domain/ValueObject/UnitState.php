<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * Where one unit of the day's programme — a word, a phrase, the learner's line of an exchange —
 * stands in the day window (DAY-UI-2): not walked yet, walked, or failed twice and coming back on
 * the next day.
 */
enum UnitState: string
{
    case Pending = 'pending';
    case Done = 'done';
    case ReturnsTomorrow = 'returns_tomorrow';
}
