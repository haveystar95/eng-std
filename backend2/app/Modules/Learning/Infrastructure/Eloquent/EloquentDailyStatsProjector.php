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

        // Intros first, and they touch `new` alone: an exposure is not a review, so it must not
        // move the review count, the correct count or the study seconds. Counting them first is
        // also what stops a term met and answered in one batch being counted twice — the review
        // loop below skips anything already counted here.
        foreach ($event->exposures as $exposure) {
            $userId = $exposure->userId->value;
            $date = $exposure->shownAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            $key = $userId . '|' . $date;

            $buckets[$key] ??= ['user_id' => $userId, 'date' => $date, 'reviews' => 0, 'correct' => 0, 'seconds' => 0, 'new' => 0];

            $termId = $exposure->termId->value;
            if (isset($countedNewTerms[$termId])) {
                continue;
            }
            $countedNewTerms[$termId] = true;
            $buckets[$key]['new']++;
        }

        foreach ($reviews as $review) {
            $userId = $review->userId->value;
            $date = $review->answeredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            $key = $userId . '|' . $date;

            $buckets[$key] ??= ['user_id' => $userId, 'date' => $date, 'reviews' => 0, 'correct' => 0, 'seconds' => 0, 'new' => 0];

            $buckets[$key]['reviews']++;
            // What the ROW says, not what the grade implies. `Review::isCorrect()` is «anything but
            // again» — the same fact the `is_correct` column carries and the same one the learner
            // was shown when they answered. Counting `good || easy` here instead (the grade's own
            // stricter question, now named isConfidentRecall) put 4 in this column on a day with 11
            // right answers out of 12: `hard` is generous on the recognition rungs, where it largely
            // measures the pause between two taps. See QA-11.
            if ($review->isCorrect()) {
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
