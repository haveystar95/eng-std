<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Event\ReviewsSubmitted;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Folds accepted reviews into `daily_user_stats`. Runs synchronously inside the submit
 * transaction; the table stays a pure projection of the reviews log, so it can be rebuilt
 * by replaying reviews if this ever drifts.
 *
 * Dates are bucketed in UTC for now. Streaks in the user's own timezone are a Presentation
 * concern that will read the user's `timezone` when those endpoints are built.
 */
final class EloquentDailyStatsProjector implements StatsProjector
{
    private const TABLE = 'daily_user_stats';

    public function project(ReviewsSubmitted $event): void
    {
        $introduced = array_flip($event->introducedTermIds);
        $reviews = $event->accepted;
        usort($reviews, static fn ($a, $b): int => $a->answeredAt <=> $b->answeredAt);

        /** @var array<string, array{user_id: string, date: string, reviews: int, correct: int, seconds: int, new: int}> $buckets */
        $buckets = [];
        $countedNewTerms = [];

        foreach ($reviews as $review) {
            $userId = $review->userId->value;
            $date = $review->answeredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            $key = $userId . '|' . $date;

            $buckets[$key] ??= ['user_id' => $userId, 'date' => $date, 'reviews' => 0, 'correct' => 0, 'seconds' => 0, 'new' => 0];

            $buckets[$key]['reviews']++;
            if ($review->grade->isCorrect()) {
                $buckets[$key]['correct']++;
            }
            $buckets[$key]['seconds'] += intdiv(max(0, $review->latencyMs ?? 0), 1000);

            $termId = $review->termId->value;
            if (isset($introduced[$termId]) && ! isset($countedNewTerms[$termId])) {
                $countedNewTerms[$termId] = true;
                $buckets[$key]['new']++;
            }
        }

        foreach ($buckets as $bucket) {
            $this->applyIncrements($bucket);
        }
    }

    /** @param array{user_id: string, date: string, reviews: int, correct: int, seconds: int, new: int} $bucket */
    private function applyIncrements(array $bucket): void
    {
        $key = ['user_id' => $bucket['user_id'], 'date' => $bucket['date']];

        $existing = DB::table(self::TABLE)->where($key)->first();
        if ($existing !== null) {
            DB::table(self::TABLE)->where($key)->update([
                'reviews_count' => (int) $existing->reviews_count + $bucket['reviews'],
                'new_terms_count' => (int) $existing->new_terms_count + $bucket['new'],
                'correct_count' => (int) $existing->correct_count + $bucket['correct'],
                'study_seconds' => (int) $existing->study_seconds + $bucket['seconds'],
            ]);

            return;
        }

        DB::table(self::TABLE)->insert([
            ...$key,
            'reviews_count' => $bucket['reviews'],
            'new_terms_count' => $bucket['new'],
            'correct_count' => $bucket['correct'],
            'study_seconds' => $bucket['seconds'],
        ]);
    }
}
