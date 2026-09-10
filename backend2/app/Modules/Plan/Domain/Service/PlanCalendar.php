<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\DayType;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * THE CALENDAR OF A PLAN — one to ten days, laid out by one rule (`docs/plan-v2.md` §7).
 *
 *   1 → [scene]
 *   2 → [scene, scene]
 *   3 → [scene, scene, rehearsal]
 *   ≥4 → scenes, every third day a review, the last day the rehearsal
 *
 * Pure: a function of the number of days and nothing else. The server counts the days — the
 * model never sees a calendar — and the number of scenes a plan asks the model for is a fact
 * derived from this layout, not a second opinion beside it.
 */
final class PlanCalendar
{
    public const MIN_DAYS = 1;

    public const MAX_DAYS = 10;

    /** @return list<DayType> one entry per day, day 1 first */
    public static function layout(int $daysTotal): array
    {
        self::assertDays($daysTotal);

        if ($daysTotal === 1) {
            return [DayType::Scene];
        }
        if ($daysTotal === 2) {
            return [DayType::Scene, DayType::Scene];
        }
        if ($daysTotal === 3) {
            return [DayType::Scene, DayType::Scene, DayType::Rehearsal];
        }

        $days = [];
        for ($number = 1; $number < $daysTotal; $number++) {
            $days[] = $number % 3 === 0 ? DayType::Review : DayType::Scene;
        }
        $days[] = DayType::Rehearsal;

        return $days;
    }

    /** How many scenes the model is asked for — the number of scene days. */
    public static function scenesCount(int $daysTotal): int
    {
        return count(array_filter(self::layout($daysTotal), static fn (DayType $t): bool => $t === DayType::Scene));
    }

    /**
     * How many days the calendar has room for before the event: the event's own day is not a study
     * day, and a plan always has at least one day — today.
     */
    public static function daysUntil(DateTimeImmutable $today, DateTimeImmutable $eventDate): int
    {
        return max(self::MIN_DAYS, min(self::MAX_DAYS, self::calendarDaysBetween($today, $eventDate)));
    }

    /**
     * Whole calendar days from `$from` to `$to`, by their DATES — the two may sit in different
     * zones (a stored date is UTC midnight, the learner's «today» is their own midnight), and an
     * instant difference between them would be off by a day either side of midnight.
     */
    public static function calendarDaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $a = new DateTimeImmutable($from->format('Y-m-d'), new DateTimeZone('UTC'));
        $b = new DateTimeImmutable($to->format('Y-m-d'), new DateTimeZone('UTC'));

        return (int) $a->diff($b)->format('%r%a');
    }

    public static function assertDays(int $daysTotal): void
    {
        if ($daysTotal < self::MIN_DAYS || $daysTotal > self::MAX_DAYS) {
            throw new InvalidArgumentException("A plan has 1 to 10 days, not {$daysTotal}.");
        }
    }
}
