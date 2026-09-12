<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * THE REMINDER HOUR — ONE RULE FOR THE SERVER AND THE PHONE (доработка PLAN-UI-3, п. 1).
 *
 * The hour the learner usually comes: the last {@see WINDOW} visits, each read as a local time of
 * day, the median of them, rounded DOWN to the hour, and never earlier than {@see EARLIEST} (08:00).
 * No visits at all → {@see DEFAULT_MINUTES} (19:00) in the learner's zone. This is the only place the
 * hour is decided: the server's tick aims the daily reminder and «сегодня разговор» at it, and the
 * phone receives it in `GET /plans` (`reminder_hour`) and schedules its local reminders by it — two
 * clocks computing it separately is how a learner gets two letters at two different times.
 *
 * EVENING VISITS ACROSS MIDNIGHT. A learner who comes at 23:40, 23:55 and 00:20 comes «late in the
 * evening», not «just after midnight»: the learner's day is taken to start at {@see DAY_STARTS_AT}
 * (04:00), a visit before it counts as the tail of the previous evening (00:20 → 24:20) for the
 * median, and the answer is folded back into 00:00–23:59 at the end. What falls before 08:00 after the
 * fold — a night owl, a midnight median — is lifted to 08:00: nobody is woken by a reminder.
 */
final class UsualVisitTime
{
    public const DEFAULT_MINUTES = 19 * 60;

    /** The floor: no reminder and no «сегодня разговор» before 08:00 local, on the server and the phone. */
    public const EARLIEST = 8 * 60;

    public const WINDOW = 7;

    public const STEP = 60;

    public const DAY_STARTS_AT = 4 * 60;

    private const MINUTES_PER_DAY = 24 * 60;

    /**
     * @param  list<int>  $localMinutes  the visits' local times of day, newest first; only the first
     *                                   {@see WINDOW} are read
     * @return int minutes after local midnight: a whole hour, at or after {@see EARLIEST}
     */
    public static function of(array $localMinutes): int
    {
        $recent = array_slice($localMinutes, 0, self::WINDOW);
        if ($recent === []) {
            return self::DEFAULT_MINUTES;
        }

        $shifted = [];
        foreach ($recent as $minutes) {
            if ($minutes < 0 || $minutes >= self::MINUTES_PER_DAY) {
                throw new InvalidArgumentException("A time of day is 0…1439 minutes, got {$minutes}.");
            }
            $shifted[] = $minutes < self::DAY_STARTS_AT ? $minutes + self::MINUTES_PER_DAY : $minutes;
        }
        sort($shifted);

        $count = count($shifted);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $shifted[$middle]
            : intdiv($shifted[$middle - 1] + $shifted[$middle], 2);

        $hour = (intdiv($median, self::STEP) * self::STEP) % self::MINUTES_PER_DAY;

        return max($hour, self::EARLIEST);
    }

    /** The local time of day of an instant already set to the learner's zone. */
    public static function minutesOf(DateTimeImmutable $local): int
    {
        return (int) $local->format('G') * 60 + (int) $local->format('i');
    }
}
