<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-11, through the real pipeline: `daily_user_stats.correct_count` must mean the same thing as
 * `reviews.is_correct`, because both are shown to the same person about the same day.
 *
 * Historical rows are deliberately NOT recomputed — the projection is append-only in practice and
 * the existing days are test data. Only days counted from here on are counted the new way.
 */
it('counts the day correct answers the way the review rows do', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'withdraw cash', 'снять наличные');

    // A slow-but-right answer: the grader gives `hard` when the answer is correct and the latency is
    // over the mode's median. This is the ordinary case on the recognition rungs, where the pause is
    // the learner reading the feedback rather than a weak memory.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing',
            'response' => 'withdraw cash', 'answered_at' => now()->toIso8601String(),
            'client_seq' => 1, 'latency_ms' => 30_000,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $review = DB::table('reviews')->where('user_id', $user->id)->first();
    expect($review->grade)->toBe('hard', 'the case the two definitions disagreed about');
    expect((bool) $review->is_correct)->toBeTrue();

    $day = DB::table('daily_user_stats')->where('user_id', $user->id)->first();
    expect((int) $day->reviews_count)->toBe(1);
    expect((int) $day->correct_count)->toBe(1, 'this column read 0 for a right answer before QA-11');
});

it('still does not count a wrong answer as correct', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'withdraw cash', 'снять наличные');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing',
            'response' => 'something else entirely', 'answered_at' => now()->toIso8601String(),
            'client_seq' => 1,
        ]]])
        ->assertOk();

    $day = DB::table('daily_user_stats')->where('user_id', $user->id)->first();
    expect((int) $day->reviews_count)->toBe(1);
    expect((int) $day->correct_count)->toBe(0);
});
