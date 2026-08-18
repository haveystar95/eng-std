<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\SubmitReviews;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE LADDER'S COUNTER (QA-18) — what makes a review "successful", and what the rung is read off.
 *
 * The rungs above assembly used to be read off the scheduler's `reps`, which counts CALLS: every
 * grade, `again` included. And because an `again` in the learning state re-schedules the pair with
 * a 0-day interval, the pair comes straight back — so a word nobody could remember rode its own
 * failures up to dictation. `reps` still exists, still counts calls, and still drives the mode
 * rotation; the ladder now counts something else, in its own column.
 *
 * These tests pin the increment rule from the outside, through the real fold, because the rule is
 * about a batch (practice or not, before or after graduation) rather than about one entity method.
 */

/** The fake answer key accepts "correct"; a matching response grades `good`, anything else `again`. */
function counted(
    TermId $termId,
    bool $correct,
    string $answeredAt,
    int $seq,
    bool $isPractice = false,
    bool $usedHint = false,
): ReviewInput {
    return new ReviewInput(
        reviewId: ReviewId::generate(),
        termId: $termId,
        exerciseMode: ExerciseMode::Typing,
        response: $correct ? 'correct' : 'wrong',
        answeredAt: new DateTimeImmutable($answeredAt),
        clientSeq: $seq,
        isPractice: $isPractice,
        usedHint: $usedHint,
    );
}

/** Two correct answers walk a fresh pair up the recognition rungs and off the ladder. */
function walkOffLadder(callable $handler, UserId $user, TermId $term): void
{
    $handler(new SubmitReviews($user, [
        counted($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', seq: 1),
        counted($term, correct: true, answeredAt: '2026-07-27T10:05:00Z', seq: 2),
    ]));
}

beforeEach(function () {
    $this->user = UserId::generate();
});

it('counts a correct non-practice review of a graduated pair, and nothing else', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    walkOffLadder($handler, $this->user, $term);

    expect($this->progress->get($this->user, $term)?->successfulReviews())
        ->toBe(0, 'the two recognition steps were passed BEFORE graduation, so neither counts');

    $handler(new SubmitReviews($this->user, [
        counted($term, correct: true, answeredAt: '2026-07-28T10:00:00Z', seq: 3),
    ]));

    expect($this->progress->get($this->user, $term)?->successfulReviews())->toBe(1);
});

it('counts `hard` — recalling it with a stumble is recalling it', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    walkOffLadder($handler, $this->user, $term);

    // A right answer that leaned on the hint: correct, and graded `hard` for it.
    $handler(new SubmitReviews($this->user, [
        counted($term, correct: true, answeredAt: '2026-07-28T10:00:00Z', seq: 3, usedHint: true),
    ]));

    $reviews = $this->reviews->all();
    expect(end($reviews)->grade)->toBe(Grade::Hard)
        ->and($this->progress->get($this->user, $term)?->successfulReviews())->toBe(1);
});

it('does not count `again`, and does not reset the counter either', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    walkOffLadder($handler, $this->user, $term);

    $handler(new SubmitReviews($this->user, [
        counted($term, correct: true, answeredAt: '2026-07-28T10:00:00Z', seq: 3),
        counted($term, correct: true, answeredAt: '2026-07-29T10:00:00Z', seq: 4),
    ]));
    expect($this->progress->get($this->user, $term)?->successfulReviews())->toBe(2);

    $handler(new SubmitReviews($this->user, [
        counted($term, correct: false, answeredAt: '2026-07-30T10:00:00Z', seq: 5),
        counted($term, correct: false, answeredAt: '2026-07-30T10:01:00Z', seq: 6),
    ]));

    // Two lapses, and the pair keeps its rung. Deliberate, and the one place this differs from an
    // FSRS-shaped model: the rung decides which TRAINERS a word may appear in, and taking typing
    // away after one bad evening removes the trainer that was doing the work.
    expect($this->progress->get($this->user, $term)?->successfulReviews())->toBe(2);
});

it('does not count a correct answer given before the pair graduated', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    // Rung 1 → rung 2: both correct, both real retrievals, both logged. They move `learning_step`,
    // which is the honest measure on the recognition rungs, and they do not touch this counter.
    $handler(new SubmitReviews($this->user, [
        counted($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', seq: 1),
    ]));

    expect($this->progress->get($this->user, $term)?->successfulReviews())->toBe(0)
        ->and($this->progress->get($this->user, $term)?->learningStep())->toBe(2);
});

it('does not count a correct PRACTICE answer', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    walkOffLadder($handler, $this->user, $term);

    $handler(new SubmitReviews($this->user, [
        counted($term, correct: true, answeredAt: '2026-07-28T10:00:00Z', seq: 3, isPractice: true),
        counted($term, correct: true, answeredAt: '2026-07-28T10:01:00Z', seq: 4, isPractice: true),
    ]));

    // Free practice keeps the streak and never touches progress. A rung is progress.
    expect($this->progress->get($this->user, $term)?->successfulReviews())->toBe(0);
});

it('is the rung, where `reps` is not — the `antipyretic` shape, replayed', function () {
    // The owner's live case: after graduating, four misses and two hits. `reps` reads 6, because
    // the scheduler was called six times; the pair has been recalled twice. Before this change the
    // ladder read the 6 and dealt DICTATION to a word that had mostly been forgotten.
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    walkOffLadder($handler, $this->user, $term);

    $handler(new SubmitReviews($this->user, [
        counted($term, correct: false, answeredAt: '2026-07-28T10:00:00Z', seq: 3),
        counted($term, correct: false, answeredAt: '2026-07-28T11:00:00Z', seq: 4),
        counted($term, correct: true, answeredAt: '2026-07-28T12:00:00Z', seq: 5),
        counted($term, correct: false, answeredAt: '2026-07-29T10:00:00Z', seq: 6),
        counted($term, correct: false, answeredAt: '2026-07-29T11:00:00Z', seq: 7),
        counted($term, correct: true, answeredAt: '2026-07-29T12:00:00Z', seq: 8),
    ]));

    $progress = $this->progress->get($this->user, $term);
    expect($progress?->reps())->toBe(6, 'the scheduler was called six times — that much was true')
        ->and($progress?->successfulReviews())->toBe(2)
        ->and($progress?->ladderStep())->toBe(3, 'assembly — typing is not earned yet');
});
