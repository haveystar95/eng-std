<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\SubmitReviews;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Free practice must work in airplane mode from start to summary, which means the DEVICE mints the
 * session id — the server has never seen it when the answers finally arrive. Such a session is
 * adopted rather than rejected: practice schedules nothing, so the composition guard (which exists
 * to stop an abandoned session spending the daily new-word quota on unseen terms) has nothing to
 * protect here.
 *
 * Adoption is also the one place a client can create a server row it did not go through an endpoint
 * for, so the guards around it are the point of this file.
 *
 * `buildSubmitHandler` / `answer` live in SubmitReviewsTest.
 */
function practiceAnswer(TermId $termId, StudySessionId $sessionId, string $answeredAt, int $seq): ReviewInput
{
    return new ReviewInput(
        reviewId: ReviewId::generate(),
        termId: $termId,
        exerciseMode: ExerciseMode::Typing,
        response: 'correct',
        answeredAt: new DateTimeImmutable($answeredAt),
        clientSeq: $seq,
        isPractice: true,
        sessionId: $sessionId,
    );
}

function schedulingAnswer(TermId $termId, StudySessionId $sessionId, string $answeredAt, int $seq): ReviewInput
{
    return new ReviewInput(
        reviewId: ReviewId::generate(),
        termId: $termId,
        exerciseMode: ExerciseMode::Typing,
        response: 'correct',
        answeredAt: new DateTimeImmutable($answeredAt),
        clientSeq: $seq,
        isPractice: false,
        sessionId: $sessionId,
    );
}

beforeEach(function () {
    $this->user = UserId::generate();
});

it('adopts a practice session the server has never seen', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $first = TermId::generate();
    $second = TermId::generate();

    $result = $handler(new SubmitReviews($this->user, [
        practiceAnswer($second, $session, '2026-08-10T10:05:00Z', 2),
        practiceAnswer($first, $session, '2026-08-10T10:00:00Z', 1),
    ]));

    expect($result->accepted)->toBe(2)
        ->and($result->unknown)->toBe(0);

    $adopted = $this->sessions->context($session->value);
    expect($adopted)->not->toBeNull()
        ->and($adopted->isPractice)->toBeTrue()
        ->and($adopted->userId)->toBe($this->user->value)
        // started_at is the EARLIEST answer, not the order the batch happened to arrive in.
        ->and($this->sessions->startedAt($session->value)?->format(DATE_ATOM))
        ->toBe((new DateTimeImmutable('2026-08-10T10:00:00Z'))->format(DATE_ATOM));

    $reviews = $this->reviews->all();
    expect($reviews)->toHaveCount(2)
        ->and($reviews[0]->sessionId?->value)->toBe($session->value);
});

it('accepts a second chunk of the same offline run without duplicating or rewriting it', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $firstChunk = TermId::generate();
    $secondChunk = TermId::generate();

    $handler(new SubmitReviews($this->user, [
        practiceAnswer($firstChunk, $session, '2026-08-10T10:00:00Z', 1),
    ]));
    // The next chunk carries terms the first one never mentioned — a real offline run does exactly
    // this, which is why composition is not checked for practice at all.
    $result = $handler(new SubmitReviews($this->user, [
        practiceAnswer($secondChunk, $session, '2026-08-10T10:10:00Z', 2),
    ]));

    expect($result->accepted)->toBe(1)
        ->and($result->unknown)->toBe(0)
        ->and($this->sessions->count())->toBe(1)
        ->and($this->sessions->composition($session->value))->toBe([$firstChunk->value])
        ->and($this->sessions->startedAt($session->value)?->format(DATE_ATOM))
        ->toBe((new DateTimeImmutable('2026-08-10T10:00:00Z'))->format(DATE_ATOM));
});

it('never accepts an answer against another user\'s session', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $term = TermId::generate();
    $this->sessions->seed($session->value, UserId::generate()->value, isPractice: true, termIds: [$term->value]);

    $result = $handler(new SubmitReviews($this->user, [
        practiceAnswer($term, $session, '2026-08-10T10:00:00Z', 1),
    ]));

    expect($result->unknown)->toBe(1)
        ->and($result->accepted)->toBe(0)
        ->and($this->reviews->all())->toBeEmpty()
        ->and($this->sessions->count())->toBe(1); // not adopted over, not touched
});

it('rejects a practice answer against the user\'s own SCHEDULING session', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $term = TermId::generate();
    $this->sessions->seed($session->value, $this->user->value, isPractice: false, termIds: [$term->value]);

    $result = $handler(new SubmitReviews($this->user, [
        practiceAnswer($term, $session, '2026-08-10T10:00:00Z', 1),
    ]));

    expect($result->unknown)->toBe(1)
        ->and($result->accepted)->toBe(0);
});

it('still rejects a SCHEDULING answer naming a session the server has never seen', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $term = TermId::generate();

    $result = $handler(new SubmitReviews($this->user, [
        schedulingAnswer($term, $session, '2026-08-10T10:00:00Z', 1),
    ]));

    // The regression trap: adoption must never widen to scheduling. Its composition is the guard
    // on the daily new-word quota, and a client that could mint one could mint the quota away.
    expect($result->unknown)->toBe(1)
        ->and($result->accepted)->toBe(0)
        ->and($this->sessions->has($session->value))->toBeFalse();
});

it('still rejects a scheduling answer for a term outside its own session', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $inSession = TermId::generate();
    $outside = TermId::generate();
    $this->sessions->seed($session->value, $this->user->value, isPractice: false, termIds: [$inSession->value]);

    $result = $handler(new SubmitReviews($this->user, [
        schedulingAnswer($outside, $session, '2026-08-10T10:00:00Z', 1),
    ]));

    expect($result->unknown)->toBe(1)
        ->and($result->accepted)->toBe(0);
});

it('is idempotent on a re-uploaded practice answer', function () {
    $handler = buildSubmitHandler($this);
    $session = StudySessionId::generate();
    $term = TermId::generate();
    $input = practiceAnswer($term, $session, '2026-08-10T10:00:00Z', 1);

    $first = $handler(new SubmitReviews($this->user, [$input]));
    $second = $handler(new SubmitReviews($this->user, [$input]));

    expect($first->accepted)->toBe(1)
        ->and($second->accepted)->toBe(0)
        ->and($second->duplicates)->toBe(1)
        ->and($this->reviews->all())->toHaveCount(1)
        ->and($this->sessions->count())->toBe(1);
});
