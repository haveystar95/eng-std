<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use Illuminate\Support\Facades\DB;

final class EloquentReviewRepository implements ReviewRepository
{
    public function insertIgnore(Review $review): bool
    {
        $inserted = DB::table('reviews')->insertOrIgnore([
            'id' => $review->id->value,
            'user_id' => $review->userId->value,
            'term_id' => $review->termId->value,
            'session_id' => $review->sessionId?->value,
            'grade' => $review->grade->value,
            'exercise_mode' => $review->exerciseMode?->value,
            'ladder_step' => $review->ladderStep,
            'is_correct' => $review->isCorrect(),
            'is_practice' => $review->isPractice,
            'is_verification' => $review->isVerification,
            'response' => $review->response,
            'latency_ms' => $review->latencyMs,
            'client_seq' => $review->clientSeq,
            // The device stamps this in ITS OWN zone, so it is exactly the binding that loses an
            // offset (see UtcInstant). Left as-is, an answer given at 23:30 in Bucharest was stored
            // as 23:30Z and read back as 02:30 the next day — the streak and the activity calendar
            // both bucket by the learner's local date, so the evening's work credited tomorrow.
            'answered_at' => UtcInstant::bind($review->answeredAt),
            'created_at' => now(),
        ]);

        return $inserted === 1;
    }
}
