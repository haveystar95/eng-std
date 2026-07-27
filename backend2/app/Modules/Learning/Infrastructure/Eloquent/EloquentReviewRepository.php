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
            'latency_ms' => $review->latencyMs,
            'answered_at' => $review->answeredAt,
            'created_at' => now(),
        ]);

        return $inserted === 1;
    }
}
