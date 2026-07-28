<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Learning\Application\Port\StatsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentStatsReader implements StatsReader
{
    private const MASTERED_INTERVAL_DAYS = 21;

    public function read(UserId $userId, DateTimeImmutable $now): StatsView
    {
        $uid = $userId->value;

        $totalTerms = DB::table('user_term_progress')->where('user_id', $uid)->count();
        $learned = DB::table('user_term_progress')->where('user_id', $uid)->where('state', 'review')->count();
        $mastered = DB::table('user_term_progress')
            ->where('user_id', $uid)->where('state', 'review')
            ->where('interval_days', '>=', self::MASTERED_INTERVAL_DAYS)->count();
        $dueToday = DB::table('user_term_progress')
            ->where('user_id', $uid)->where('state', '<>', 'new')
            ->where('due_at', '<=', $now)->count();

        $today = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $reviewsToday = (int) DB::table('daily_user_stats')
            ->where('user_id', $uid)->where('date', $today)->value('reviews_count');

        return new StatsView(
            totalTerms: $totalTerms,
            learned: $learned,
            mastered: $mastered,
            dueToday: $dueToday,
            reviewsToday: $reviewsToday,
            streakDays: $this->streak($uid, $now),
        );
    }

    private function streak(string $uid, DateTimeImmutable $now): int
    {
        $days = [];
        foreach (DB::table('daily_user_stats')->where('user_id', $uid)->where('reviews_count', '>', 0)->pluck('date') as $date) {
            $days[(string) $date] = true;
        }

        $day = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        // A streak may run up to today; if nothing is done yet today, count back from yesterday.
        if (! isset($days[$day->format('Y-m-d')])) {
            $day = $day->modify('-1 day');
        }

        $streak = 0;
        while (isset($days[$day->format('Y-m-d')])) {
            $streak++;
            $day = $day->modify('-1 day');
        }

        return $streak;
    }
}
