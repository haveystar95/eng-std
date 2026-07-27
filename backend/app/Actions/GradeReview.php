<?php

namespace App\Actions;

use App\Models\ReviewLog;
use App\Models\ReviewState;
use App\Models\User;
use App\Models\Word;
use App\Services\Fsrs;
use Carbon\CarbonImmutable;

/** Applies an FSRS rating to a word, persists the state, and logs the review. */
class GradeReview
{
    public function __construct(private readonly Fsrs $fsrs) {}

    public function handle(User $user, Word $word, int $rating): ReviewState
    {
        $state = ReviewState::firstOrNew([
            'user_id' => $user->id,
            'word_id' => $word->id,
        ]);

        $now = CarbonImmutable::now();
        $elapsed = $state->last_reviewed_at
            ? max(0, (int) $state->last_reviewed_at->diffInDays($now))
            : 0;

        $this->fsrs->review($state, $rating, $now);
        $state->save();

        ReviewLog::create([
            'user_id' => $user->id,
            'word_id' => $word->id,
            'rating' => $rating,
            'stability_after' => $state->stability,
            'difficulty_after' => $state->difficulty,
            'elapsed_days' => $elapsed,
            'reviewed_at' => $now,
        ]);

        return $state;
    }
}
