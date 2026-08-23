<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Learning\Application\Port\StatsReader;
use App\Modules\Learning\Domain\Service\Mastery;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentStatsReader implements StatsReader
{
    /** How far back the activity calendar reaches (a year heatmap + headroom for the streak). */
    private const ACTIVITY_WINDOW_DAYS = 365;

    public function read(UserId $userId, DateTimeImmutable $now, DateTimeZone $tz, int $newGoal, int $newToday): StatsView
    {
        $uid = $userId->value;

        $totalTerms = DB::table('user_term_progress')->where('user_id', $uid)->count();
        $learned = DB::table('user_term_progress')->where('user_id', $uid)->where('state', 'review')->count();
        // Same definition as Mastery::isMastered, expressed in SQL for an aggregate count.
        $mastered = DB::table('user_term_progress')
            ->where('user_id', $uid)
            ->where(function (Builder $q): void {
                $q->where('state', 'known')
                    ->orWhere(function (Builder $q2): void {
                        $q2->where('state', 'review')->where('interval_days', '>=', Mastery::MASTERED_INTERVAL_DAYS);
                    });
            })->count();
        // «Повторить N» has to be the number of cards a session would actually deal, or the home
        // screen offers a session that comes back empty. Two populations, matching the reader that
        // builds the session: pool pairs whose due date has come, and `known` verifications — which
        // are out of the pool on purpose and still get checked (see EloquentDueTermsReader).
        $dueToday = DB::table('user_term_progress')
            ->where('user_id', $uid)->where('state', '<>', 'new')
            ->where('due_at', '<=', $now)
            ->where(function (Builder $q): void {
                $q->whereNotNull('enrolled_at')->orWhere('state', 'known');
            })
            ->count();

        // Activity is derived live from the append-only review log in the user's timezone — no
        // client state to lose on relogin, and a late-arriving (offline/end-of-session) practice
        // batch credits its own day the moment it lands, because its answered_at names that day.
        $today = $now->setTimezone($tz)->format('Y-m-d');
        $activeDays = $this->activeDays($uid, $now, $tz);
        $reviewsToday = $this->reviewsOn($uid, $today, $tz);

        return new StatsView(
            totalTerms: $totalTerms,
            learned: $learned,
            mastered: $mastered,
            dueToday: $dueToday,
            reviewsToday: $reviewsToday,
            streakDays: $this->streak($activeDays, $today),
            activeDays: $activeDays,
            newGoal: $newGoal,
            newToday: $newToday,
        );
    }

    /**
     * Distinct local calendar dates (user timezone) that have >=1 review of any kind (study or
     * practice) within the activity window, oldest first.
     *
     * @return list<string>
     */
    private function activeDays(string $uid, DateTimeImmutable $now, DateTimeZone $tz): array
    {
        // Start of the learner's day, a year back — computed IN their zone and then bound as the
        // instant it is: bound raw, the offset is dropped and the window edge moves by it (UtcInstant).
        $windowStart = UtcInstant::bind(
            $now->modify('-' . self::ACTIVITY_WINDOW_DAYS . ' days')->setTimezone($tz)->setTime(0, 0, 0),
        );

        $rows = DB::table('reviews')
            ->where('user_id', $uid)
            ->where('answered_at', '>=', $windowStart)
            ->distinct()
            ->orderBy('d')
            ->selectRaw('(answered_at AT TIME ZONE ?)::date AS d', [$tz->getName()])
            ->pluck('d');

        return array_values(array_map(
            static fn ($d): string => (new DateTimeImmutable((string) $d))->format('Y-m-d'),
            $rows->all(),
        ));
    }

    private function reviewsOn(string $uid, string $localDate, DateTimeZone $tz): int
    {
        return DB::table('reviews')
            ->where('user_id', $uid)
            ->whereRaw('(answered_at AT TIME ZONE ?)::date = ?', [$tz->getName(), $localDate])
            ->count();
    }

    /**
     * Consecutive active days ending today (or yesterday, if today has no review yet), counted on
     * the user's local calendar dates.
     *
     * @param  list<string>  $activeDays  ascending local dates
     */
    private function streak(array $activeDays, string $today): int
    {
        $set = array_flip($activeDays);

        $day = new DateTimeImmutable($today);
        // A streak may run up to today; if nothing is done yet today, count back from yesterday.
        if (! isset($set[$day->format('Y-m-d')])) {
            $day = $day->modify('-1 day');
        }

        $streak = 0;
        while (isset($set[$day->format('Y-m-d')])) {
            $streak++;
            $day = $day->modify('-1 day');
        }

        return $streak;
    }
}
