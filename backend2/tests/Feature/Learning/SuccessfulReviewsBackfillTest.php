<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * THE BACKFILL of the ladder's counter (QA-18), run against the real migration statement.
 *
 * A migration is a one-shot script nobody re-reads, and this one decides what rung every word the
 * owner already owns starts on. So the migration exposes its UPDATE as `backfill()` and this test
 * calls THAT — a transcribed copy of the SQL would pass while the shipped statement was wrong.
 *
 * The history replayed here is `antipyretic`'s own, from the owner's database: eight reviews, two
 * recognition taps and six graduated answers of which two were right. The old ladder read `reps`,
 * saw 6, and dealt dictation. The honest answer is 2 — rung 3, assembly.
 */
function runBackfill(): void
{
    $migration = require base_path('app/Modules/Learning/Infrastructure/Migration/2026_08_18_100000_add_successful_reviews_to_progress.php');
    $migration->backfill();
}

/** One row of the append-only log, as the owner's database holds it. */
function loggedReview(
    string $userId,
    string $termId,
    string $grade,
    bool $isCorrect,
    ?int $ladderStep,
    int $seq,
    bool $isPractice = false,
): void {
    DB::table('reviews')->insert([
        'id' => Str::ulid()->toBase32(),
        'user_id' => $userId,
        'term_id' => $termId,
        'grade' => $grade,
        'is_correct' => $isCorrect,
        'is_practice' => $isPractice,
        'is_verification' => false,
        'ladder_step' => $ladderStep,
        'client_seq' => $seq,
        'answered_at' => now()->subDays(10)->addMinutes($seq),
        'created_at' => now(),
    ]);
}

beforeEach(function () {
    [$user] = learner();
    $this->userId = $user->id;
    $this->termId = seedWordFor($user, 'antipyretic', 'жаропонижающее');

    DB::table('user_term_progress')->insert([
        'user_id' => $this->userId,
        'term_id' => $this->termId,
        'state' => LearningState::Learning->value,
        'acquisition' => Acquisition::Graduated->value,
        'learning_step' => 0,
        'reps' => 6,
        'successful_reviews' => 0,
        'lapses' => 0,
        'ease_factor' => 2.5,
        'interval_days' => 0,
        'due_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it("rebuilds `antipyretic`'s counter from the log: eight reviews, two successes", function () {
    // Verbatim from the owner's database, in client_seq order.
    loggedReview($this->userId, $this->termId, 'good', true, 1, 1);   // recognition forward
    loggedReview($this->userId, $this->termId, 'good', true, 2, 2);   // recognition reverse
    loggedReview($this->userId, $this->termId, 'again', false, 3, 3);
    loggedReview($this->userId, $this->termId, 'again', false, 3, 4);
    loggedReview($this->userId, $this->termId, 'hard', true, 3, 5);   // counts
    loggedReview($this->userId, $this->termId, 'again', false, 3, 6);
    loggedReview($this->userId, $this->termId, 'again', false, 4, 7);
    loggedReview($this->userId, $this->termId, 'hard', true, 4, 8);   // counts

    runBackfill();

    $row = DB::table('user_term_progress')
        ->where('user_id', $this->userId)->where('term_id', $this->termId)->first();

    expect((int) $row->successful_reviews)->toBe(2)
        ->and((int) $row->reps)->toBe(6, 'the scheduler counter is not touched by the backfill')
        ->and(LearningLadder::stepFor(Acquisition::Graduated, (int) $row->successful_reviews, 0))
        ->toBe(LearningLadder::STEP_ASSEMBLY);
});

it('counts the pre-ladder history, whose rung was never recorded', function () {
    // `ladder_step` is null for every review written before the recognition rungs existed — and all
    // of that history is graduated, because there was nowhere else to be. Reading NULL as "unknown,
    // skip it" would silently demote every word the owner learned before the ladder landed.
    loggedReview($this->userId, $this->termId, 'good', true, null, 1);
    loggedReview($this->userId, $this->termId, 'good', true, null, 2);
    loggedReview($this->userId, $this->termId, 'again', false, null, 3);

    runBackfill();

    expect((int) DB::table('user_term_progress')
        ->where('user_id', $this->userId)->where('term_id', $this->termId)->value('successful_reviews'))
        ->toBe(2);
});

it('ignores free practice and the recognition rungs', function () {
    loggedReview($this->userId, $this->termId, 'good', true, 3, 1, isPractice: true);
    loggedReview($this->userId, $this->termId, 'good', true, 4, 2, isPractice: true);
    loggedReview($this->userId, $this->termId, 'good', true, 1, 3);
    loggedReview($this->userId, $this->termId, 'good', true, 2, 4);

    runBackfill();

    // Practice never touched progress, so it never earned a rung; rungs 1–2 are not graduated.
    expect((int) DB::table('user_term_progress')
        ->where('user_id', $this->userId)->where('term_id', $this->termId)->value('successful_reviews'))
        ->toBe(0);
});

it('leaves a pair with no history at zero rather than at null', function () {
    runBackfill();

    expect((int) DB::table('user_term_progress')
        ->where('user_id', $this->userId)->where('term_id', $this->termId)->value('successful_reviews'))
        ->toBe(0);
});
