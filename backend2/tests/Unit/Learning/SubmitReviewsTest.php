<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\SubmitReviews;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The fake answer key accepts "correct"; the server grades a matching response as good, else again. */
function answer(TermId $termId, bool $correct, string $answeredAt, bool $isPractice = false, int $seq = 1): ReviewInput
{
    return new ReviewInput(
        reviewId: ReviewId::generate(),
        termId: $termId,
        exerciseMode: ExerciseMode::Typing,
        response: $correct ? 'correct' : 'wrong',
        answeredAt: new DateTimeImmutable($answeredAt),
        clientSeq: $seq,
        isPractice: $isPractice,
    );
}

// buildSubmitHandler() lives in tests/Pest.php — shared with OfflinePracticeSessionTest.

beforeEach(function () {
    $this->user = UserId::generate();
});

it('grades raw answers server-side and walks the acquisition ladder, in order', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    // A pair with no row starts on the ladder, so these two answers are its recognition steps:
    // rung 1 → rung 2 → graduated. They are real retrievals (logged, they keep the streak) but
    // they do not schedule, so SM-2 has still never touched this pair.
    $result = $handler(new SubmitReviews($this->user, [
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', seq: 1),
        answer($term, correct: true, answeredAt: '2026-07-27T10:05:00Z', seq: 2),
    ]));

    expect($result->accepted)->toBe(2)
        ->and($result->duplicates)->toBe(0)
        ->and($result->unknown)->toBe(0);

    $progress = $this->progress->get($this->user, $term);
    expect($progress?->acquisition())->toBe(Acquisition::Graduated)
        ->and($progress?->ladderStep())->toBe(3)
        // Not one scheduling field moved: graduation invents no interval.
        ->and($progress?->state())->toBe(LearningState::New)
        ->and($progress?->intervalDays())->toBe(0)
        ->and($progress?->dueAt())->toBeNull()
        ->and($progress?->reps())->toBe(0);
});

it('enters SM-2 on the first grade AFTER graduation, exactly as a new word always has', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    $handler(new SubmitReviews($this->user, [
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', seq: 1),  // rung 1 → 2
        answer($term, correct: true, answeredAt: '2026-07-27T10:05:00Z', seq: 2),  // rung 2 → graduated
        answer($term, correct: true, answeredAt: '2026-07-28T10:00:00Z', seq: 3),  // first SRS review
        answer($term, correct: true, answeredAt: '2026-07-29T10:00:00Z', seq: 4),  // graduates in SM-2
    ]));

    $progress = $this->progress->get($this->user, $term);
    expect($progress?->state())->toBe(LearningState::Review)
        ->and($progress?->intervalDays())->toBe(4)     // the historic graduating interval
        ->and($progress?->reps())->toBe(2);            // counted from graduation, not from meeting 1
});

it('re-queues a failed recognition step without writing anything to the schedule', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    $handler(new SubmitReviews($this->user, [
        answer($term, correct: false, answeredAt: '2026-07-27T10:00:00Z', seq: 1),
        answer($term, correct: false, answeredAt: '2026-07-27T10:02:00Z', seq: 2), // the re-queued card
    ]));

    $progress = $this->progress->get($this->user, $term);
    // The pair stays exactly where it was — which is what lets the client put the same card back
    // into the tail of the session — and no interval, due date or lapse was invented.
    expect($progress?->acquisition())->toBe(Acquisition::New)
        ->and($progress?->ladderStep())->toBe(0)
        ->and($progress?->intervalDays())->toBe(0)
        ->and($progress?->dueAt())->toBeNull()
        ->and($progress?->reps())->toBe(0)
        ->and($progress?->lapses())->toBe(0)
        // Both failures are still in the log: they were real retrievals, they simply failed.
        ->and($this->reviews->count())->toBe(2);
});

it('projects a stats event carrying the introduced term and invalidates the median cache', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    $handler(new SubmitReviews($this->user, [answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z')]));

    expect($this->stats->events)->toHaveCount(1)
        ->and($this->stats->events[0]->accepted)->toHaveCount(1)
        ->and($this->stats->events[0]->introducedTermIds)->toBe([$term->value])
        ->and($this->median->forgotten)->toBe(1); // typing touched → cache forgotten once
});

it('ignores a re-uploaded batch (idempotent by review id)', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    $batch = new SubmitReviews($this->user, [
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z'),
        answer($term, correct: true, answeredAt: '2026-07-27T10:05:00Z'),
    ]);

    $handler($batch);
    $before = $this->progress->get($this->user, $term);

    $second = $handler($batch); // same review ids

    expect($second->accepted)->toBe(0)
        ->and($second->duplicates)->toBe(2)
        ->and($this->reviews->count())->toBe(2)
        ->and($this->progress->get($this->user, $term))->toEqual($before)
        ->and($this->stats->events)->toHaveCount(1); // no event on the no-op replay
});

it('folds an out-of-order offline batch by client_seq, not upload or answered_at order', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    // True order is client_seq: correct(1) then wrong(2). It is uploaded reversed AND the device
    // clock even stamped the genuinely-later "wrong" with an EARLIER answered_at — both are red
    // herrings. Folded by seq (correct→wrong) the pair climbs to rung 2 and then stays there.
    // Folding by answered_at (wrong→correct) would instead have it at rung 2 from the wrong start
    // — same rung, different history — so the assertion that separates them is `learningStep`
    // after a THIRD answer, below.
    $handler(new SubmitReviews($this->user, [
        answer($term, correct: false, answeredAt: '2026-07-27T09:00:00Z', seq: 2),
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', seq: 1),
    ]));

    $progress = $this->progress->get($this->user, $term);
    expect($progress?->acquisition())->toBe(Acquisition::Learning)
        ->and($progress?->ladderStep())->toBe(2)   // correct advanced it; the later wrong held it
        ->and($progress?->intervalDays())->toBe(0);
});

it('folds a graduated pair out of order by client_seq, not by the device clock', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    // Already off the ladder, so these two answers go to SM-2 — where the order genuinely changes
    // the interval and the invariant has teeth.
    $this->progress->save(TermProgress::reconstitute(
        $this->user, $term, LearningState::New, 2.5, 0, null, 0, 0, null,
        acquisition: Acquisition::Graduated,
    ));

    $handler(new SubmitReviews($this->user, [
        answer($term, correct: false, answeredAt: '2026-07-27T09:00:00Z', seq: 2),
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', seq: 1),
    ]));

    $progress = $this->progress->get($this->user, $term);
    // By seq: new→learning(1), then wrong keeps it learning(0). By answered_at it would have
    // graduated to review instead.
    expect($progress?->state())->toBe(LearningState::Learning)
        ->and($progress?->intervalDays())->toBe(0);
});

it('rejects answers for unknown terms and applies the rest', function () {
    $known = TermId::generate();
    $unknown = TermId::generate();
    $handler = buildSubmitHandler($this, known: [$known]);

    $result = $handler(new SubmitReviews($this->user, [
        answer($known, correct: true, answeredAt: '2026-07-27T10:00:00Z'),
        answer($unknown, correct: true, answeredAt: '2026-07-27T10:00:00Z'),
    ]));

    expect($result->accepted)->toBe(1)
        ->and($result->unknown)->toBe(1)
        ->and($this->progress->get($this->user, $known))->not->toBeNull()
        ->and($this->progress->get($this->user, $unknown))->toBeNull();
});

it('resolves a wrong answer to a known term as a failed verification, not via the scheduler', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    $this->progress->save(TermProgress::knownFromTriage($this->user, $term, new DateTimeImmutable('2026-07-27T09:00:00Z')));

    $handler(new SubmitReviews($this->user, [answer($term, correct: false, answeredAt: '2026-07-27T10:00:00Z')]));

    expect($this->progress->get($this->user, $term)?->state())->toBe(LearningState::Learning);
});

it('keeps a known term known when its verification passes, pushing the next check out', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    $this->progress->save(TermProgress::knownFromTriage($this->user, $term, new DateTimeImmutable('2026-07-27T09:00:00Z')));

    $handler(new SubmitReviews($this->user, [answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z')]));

    $p = $this->progress->get($this->user, $term);
    expect($p?->state())->toBe(LearningState::Known)
        ->and($p?->dueAt())->toEqual((new DateTimeImmutable('2026-07-27T10:00:00Z'))->modify('+90 days'));
});

it('flags a non-practice answer to a known term as a verification', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    $this->progress->save(TermProgress::knownFromTriage($this->user, $term, new DateTimeImmutable('2026-07-27T09:00:00Z')));

    $handler(new SubmitReviews($this->user, [answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z')]));

    expect($this->reviews->all()[0]->isVerification)->toBeTrue();
});

it('does not flag a practice answer to a known term as a verification', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);
    $this->progress->save(TermProgress::knownFromTriage($this->user, $term, new DateTimeImmutable('2026-07-27T09:00:00Z')));

    $handler(new SubmitReviews($this->user, [answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', isPractice: true)]));

    $review = $this->reviews->all()[0];
    expect($review->isVerification)->toBeFalse()          // practice never resolves a check
        ->and($this->progress->get($this->user, $term)?->state())->toBe(LearningState::Known); // state untouched
});

it('records a practice answer in the log but never schedules it', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    $result = $handler(new SubmitReviews($this->user, [
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z', isPractice: true),
    ]));

    expect($result->accepted)->toBe(1)
        ->and($this->reviews->count())->toBe(1)                       // appended to the log
        ->and($this->progress->get($this->user, $term))->toBeNull()  // but progress untouched
        ->and($this->stats->events[0]->introducedTermIds)->toBe([]); // and nothing introduced
});
