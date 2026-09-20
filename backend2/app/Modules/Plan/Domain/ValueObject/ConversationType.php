<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHICH TALK THIS IS (наряд CONV-1): the three or four turns that close a scene day, the whole
 * visit walked through on the rehearsal day, or the three or four turns that close a review day.
 *
 * It is not the day's type under another name: a day type says what the day teaches, this says how
 * long the talk runs, how many scenes it covers and what its summary reads like («вернётся завтра»
 * on a day, «повтори перед приёмом» on the rehearsal).
 */
enum ConversationType: string
{
    case Day = 'day';
    case Rehearsal = 'rehearsal';
    case Review = 'review';

    public static function forDay(DayType $day): self
    {
        return match ($day) {
            DayType::Scene => self::Day,
            DayType::Rehearsal => self::Rehearsal,
            DayType::Review => self::Review,
        };
    }

    /** The rehearsal is the last talk before the event: nothing of it «comes back tomorrow». */
    public function returnsTomorrow(): bool
    {
        return $this !== self::Rehearsal;
    }
}
