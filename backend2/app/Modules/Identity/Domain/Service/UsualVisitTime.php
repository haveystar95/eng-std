<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Service;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * WHEN THE LEARNER USUALLY COMES — the time of day a daily reminder is aimed at.
 *
 * The last {@see WINDOW} visits, each read as a local time of day (minutes after local midnight),
 * the median of them, rounded DOWN to the quarter hour. No visits at all → {@see DEFAULT_MINUTES}
 * (19:00): an evening reminder is the least wrong guess for a learner nobody has seen yet.
 *
 * EVENING VISITS ACROSS MIDNIGHT. A learner who comes at 23:40, 23:55 and 00:20 comes «late in the
 * evening», not «just after midnight» — but a plain sort puts 00:20 first and the median of
 * 00:20 / 23:40 / 23:55 lands on 23:40 by accident, and for 00:10 / 00:20 / 23:50 it lands on
 * 00:20, a whole evening off. So the learner's day is taken to start at {@see DAY_STARTS_AT}
 * (04:00): a visit before 04:00 counts as the tail of the previous evening (00:20 → 24:20) for the
 * median, and the answer is folded back into 00:00–23:59 at the end. A night owl at 01:30 every
 * day gets 01:30; a mix of 23:50 and 00:10 gets a median near midnight instead of a morning.
 * The rounding happens before the fold, so 24:10 → 24:00 → 00:00.
 */
final class UsualVisitTime
{
    public const DEFAULT_MINUTES = 19 * 60;

    public const WINDOW = 7;

    public const QUARTER = 15;

    public const DAY_STARTS_AT = 4 * 60;

    private const MINUTES_PER_DAY = 24 * 60;

    /**
     * @param  list<int>  $localMinutes  the visits' local times of day, newest first; only the first
     *                                   {@see WINDOW} are read
     * @return int minutes after local midnight, a multiple of {@see QUARTER}
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

        return (intdiv($median, self::QUARTER) * self::QUARTER) % self::MINUTES_PER_DAY;
    }

    /** The local time of day of an instant already set to the learner's zone. */
    public static function minutesOf(DateTimeImmutable $local): int
    {
        return (int) $local->format('G') * 60 + (int) $local->format('i');
    }
}
