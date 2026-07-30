<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Append a raw review row. (learner()/seedWordFor() live in StudyApiTest.) */
function seedReview(string $userId, string $termId, string $mode, int $latency, bool $correct, bool $practice = false): void
{
    DB::table('reviews')->insert([
        'id' => Ulid::generate(),
        'user_id' => $userId,
        'term_id' => $termId,
        'grade' => $correct ? 'good' : 'again',
        'exercise_mode' => $mode,
        'is_correct' => $correct,
        'is_practice' => $practice,
        'latency_ms' => $latency,
        'answered_at' => now(),
        'created_at' => now(),
    ]);
}

it('reports insufficient data below the sample threshold', function () {
    [$user] = learner();
    $term = seedWordFor($user);
    for ($i = 0; $i < 5; $i++) {
        seedReview($user->id, $term, 'typing', 2000, correct: true);
    }

    $baseline = app(LatencyMedianReader::class)->medianFor(UserId::fromString($user->id), ExerciseMode::Typing);

    expect($baseline->isKnown())->toBeFalse();
});

it('computes the per-mode median over correct, non-practice answers only', function () {
    [$user] = learner();
    $term = seedWordFor($user);

    for ($i = 0; $i < 20; $i++) {
        seedReview($user->id, $term, 'typing', 2000, correct: true);
    }
    // Noise that must be excluded — wrong answers, practice answers, and a different mode.
    for ($i = 0; $i < 10; $i++) {
        seedReview($user->id, $term, 'typing', 100, correct: false);
        seedReview($user->id, $term, 'typing', 100, correct: true, practice: true);
        seedReview($user->id, $term, 'multiple_choice', 500, correct: true);
    }

    $baseline = app(LatencyMedianReader::class)->medianFor(UserId::fromString($user->id), ExerciseMode::Typing);

    expect($baseline->medianMs)->toBe(2000);
});

it('recomputes the median after invalidation when new answers land', function () {
    [$user] = learner();
    $term = seedWordFor($user);
    $reader = app(LatencyMedianReader::class);
    $uid = UserId::fromString($user->id);

    for ($i = 0; $i < 20; $i++) {
        seedReview($user->id, $term, 'typing', 1000, correct: true);
    }
    expect($reader->medianFor($uid, ExerciseMode::Typing)->medianMs)->toBe(1000);

    for ($i = 0; $i < 20; $i++) {
        seedReview($user->id, $term, 'typing', 3000, correct: true);
    }
    $reader->forget($uid, ExerciseMode::Typing);

    // 20×1000 + 20×3000 → median interpolates to 2000.
    expect($reader->medianFor($uid, ExerciseMode::Typing)->medianMs)->toBe(2000);
});
