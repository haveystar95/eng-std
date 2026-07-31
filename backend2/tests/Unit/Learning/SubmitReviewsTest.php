<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\SubmitReviews;
use App\Modules\Learning\Application\Command\SubmitReviewsHandler;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FakeLatencyMedianReader;
use Tests\Doubles\FakeSessionCompositionReader;
use Tests\Doubles\FakeTermAnswerKeyReader;
use Tests\Doubles\FakeTermExistenceReader;
use Tests\Doubles\FixedClock;
use Tests\Doubles\ImmediateTransactionManager;
use Tests\Doubles\InMemoryReviewRepository;
use Tests\Doubles\InMemoryTermProgressRepository;
use Tests\Doubles\SpyStatsProjector;

/** The fake answer key accepts "correct"; the server grades a matching response as good, else again. */
function answer(TermId $termId, bool $correct, string $answeredAt, bool $isPractice = false): ReviewInput
{
    return new ReviewInput(
        reviewId: ReviewId::generate(),
        termId: $termId,
        exerciseMode: ExerciseMode::Typing,
        response: $correct ? 'correct' : 'wrong',
        answeredAt: new DateTimeImmutable($answeredAt),
        isPractice: $isPractice,
    );
}

/** @param list<TermId>|null $known null = all known */
function buildSubmitHandler(object $ctx, ?array $known = null): SubmitReviewsHandler
{
    $ctx->reviews = new InMemoryReviewRepository();
    $ctx->progress = new InMemoryTermProgressRepository();
    $ctx->stats = new SpyStatsProjector();
    $ctx->median = new FakeLatencyMedianReader();

    return new SubmitReviewsHandler(
        reviews: $ctx->reviews,
        progress: $ctx->progress,
        scheduler: new Sm2Scheduler(Fuzz::none()),
        terms: $known === null ? FakeTermExistenceReader::knowingAll() : FakeTermExistenceReader::knowing($known),
        answerKeys: new FakeTermAnswerKeyReader(),
        grader: new AnswerGrader(),
        median: $ctx->median,
        sessionComposition: new FakeSessionCompositionReader(),
        snapshots: $ctx->progress, // the in-memory repo doubles as the snapshot reader
        stats: $ctx->stats,
        tx: new ImmediateTransactionManager(),
        clock: new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')),
    );
}

beforeEach(function () {
    $this->user = UserId::generate();
});

it('grades raw answers server-side and folds them into progress in order', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    $result = $handler(new SubmitReviews($this->user, [
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z'), // new → learning (1)
        answer($term, correct: true, answeredAt: '2026-07-27T10:05:00Z'), // learning → review (4)
    ]));

    expect($result->accepted)->toBe(2)
        ->and($result->duplicates)->toBe(0)
        ->and($result->unknown)->toBe(0);

    $progress = $this->progress->get($this->user, $term);
    expect($progress?->state())->toBe(LearningState::Review)
        ->and($progress?->intervalDays())->toBe(4);
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

it('folds an out-of-order offline batch by answered_at, not upload order', function () {
    $term = TermId::generate();
    $handler = buildSubmitHandler($this);

    // Uploaded later-first: wrong(10:05) before correct(10:00). Answered order is correct then
    // wrong → new→learning(1) then learning→learning(0). Upload order would wrongly graduate it.
    $handler(new SubmitReviews($this->user, [
        answer($term, correct: false, answeredAt: '2026-07-27T10:05:00Z'),
        answer($term, correct: true, answeredAt: '2026-07-27T10:00:00Z'),
    ]));

    $progress = $this->progress->get($this->user, $term);
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
