<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use DateTimeImmutable;

/**
 * WHICH FACTS A CHANGE OR A CLOCK TICK PRODUCES — pure, so the journal's rules are table-tested
 * without a database.
 */
final class PlanEventRules
{
    /**
     * `event_today` waits for the morning: the tick runs every 15 minutes from local midnight, and
     * «Сегодня приём у врача» at 00:15 is a letter that wakes the learner up. The first tick of the
     * event day at or after 08:00 in the learner's zone writes it.
     */
    public const EVENT_TODAY_NOT_BEFORE_MINUTES = 8 * 60;

    /**
     * A reschedule that SHRANK the route is `days_skipped_rebuilt` (payload `{from, to}`); one that
     * kept or grew it is no event. The server has no other rebuild: it does not detect skipped days
     * by itself, so this is the only door to that kind today.
     */
    public static function forReschedule(int $daysBefore, int $daysAfter): ?PlanEventKind
    {
        return $daysAfter < $daysBefore ? PlanEventKind::DaysSkippedRebuilt : null;
    }

    /**
     * What the learner's calendar owes a plan with an event date, given what the journal already
     * holds. Dates are compared as dates in the learner's zone (`$localNow` is already in it).
     *
     * @return list<PlanEventKind>
     */
    public static function dueOnCalendar(DateTimeImmutable $eventDate, DateTimeImmutable $localNow, bool $hasEventToday, bool $hasEventPassed): array
    {
        $event = $eventDate->format('Y-m-d');
        $today = $localNow->format('Y-m-d');
        $minutes = (int) $localNow->format('G') * 60 + (int) $localNow->format('i');

        $due = [];
        if ($today === $event && ! $hasEventToday && $minutes >= self::EVENT_TODAY_NOT_BEFORE_MINUTES) {
            $due[] = PlanEventKind::EventToday;
        }
        if ($today > $event && ! $hasEventPassed) {
            $due[] = PlanEventKind::EventPassed;
        }

        return $due;
    }
}
