<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Build a term in a chosen state; ids are irrelevant to scheduling. */
function progressAt(LearningState $state, float $ease = TermProgress::DEFAULT_EASE, int $interval = 0, int $lapses = 0): TermProgress
{
    return TermProgress::reconstitute(
        UserId::generate(), TermId::generate(), $state, $ease, $interval, null, reps: 3, lapses: $lapses, lastReviewedAt: null,
    );
}

beforeEach(function () {
    $this->now = new DateTimeImmutable('2026-07-27T08:00:00Z');
    $this->scheduler = new Sm2Scheduler(Fuzz::none());
});

dataset('transitions', [
    // state, ease, interval, grade → expected state, expected interval
    'new + again stays in learning, due now'   => [LearningState::New, 2.50, 0, Grade::Again, LearningState::Learning, 0],
    'new + good enters learning'               => [LearningState::New, 2.50, 0, Grade::Good, LearningState::Learning, 1],
    'new + easy enters learning ahead'         => [LearningState::New, 2.50, 0, Grade::Easy, LearningState::Learning, 3],
    'learning + hard repeats the step'         => [LearningState::Learning, 2.50, 1, Grade::Hard, LearningState::Learning, 1],
    'learning + good graduates to review'      => [LearningState::Learning, 2.50, 1, Grade::Good, LearningState::Review, 4],
    'learning + easy graduates further'        => [LearningState::Learning, 2.50, 1, Grade::Easy, LearningState::Review, 7],
    'review + good multiplies by ease'         => [LearningState::Review, 2.50, 10, Grade::Good, LearningState::Review, 25],
    'review + hard grows slowly'               => [LearningState::Review, 2.50, 10, Grade::Hard, LearningState::Review, 12],
    'review + again lapses to relearning'      => [LearningState::Review, 2.50, 40, Grade::Again, LearningState::Relearning, 0],
    'relearning + good returns reduced'        => [LearningState::Relearning, 2.00, 0, Grade::Good, LearningState::Review, 1],
    'relearning + easy returns'                => [LearningState::Relearning, 2.00, 0, Grade::Easy, LearningState::Review, 4],
    'relearning + again holds'                 => [LearningState::Relearning, 2.00, 0, Grade::Again, LearningState::Relearning, 0],
]);

it('moves through the state machine', function (
    LearningState $state,
    float $ease,
    int $interval,
    Grade $grade,
    LearningState $expectedState,
    int $expectedInterval,
) {
    $result = $this->scheduler->schedule(progressAt($state, $ease, $interval), $grade, $this->now);

    expect($result->state())->toBe($expectedState)
        ->and($result->intervalDays())->toBe($expectedInterval);
})->with('transitions');

it('sets due_at from the interval, and due-now for a 0-day interval', function () {
    $graduated = $this->scheduler->schedule(progressAt(LearningState::Learning, interval: 1), Grade::Good, $this->now);
    expect($graduated->dueAt()?->format('Y-m-d'))->toBe('2026-07-31');

    $lapsed = $this->scheduler->schedule(progressAt(LearningState::Review, interval: 40), Grade::Again, $this->now);
    expect($lapsed->dueAt())->toEqual($this->now);
});

// ── F19: day-scale due lands at the START of the user's calendar day ─────────────

it('floors a day-scale due to the start of the user\'s day, not the moment of answering', function () {
    // Answered at 19:00 UTC with a 1-day step (new + good → learning, +1 day). Without rounding the
    // card would return at 19:00 tomorrow; F19 wants 00:00 tomorrow so it's available all day.
    $evening = new DateTimeImmutable('2026-07-27T19:00:00Z');

    $result = $this->scheduler->schedule(progressAt(LearningState::New), Grade::Good, $evening, new DateTimeZone('UTC'));

    expect($result->dueAt())->toEqual(new DateTimeImmutable('2026-07-28T00:00:00Z'));
});

it('floors to the start of the day in the USER\'s timezone, not UTC', function () {
    // Answered late on 2026-07-27 in Kyiv (UTC+3). now + 1 day = 07-28 23:00 Kyiv; floored to the
    // start of THAT day = 07-28 00:00 Kyiv (21:00Z on the 27th) — the user's calendar day, not UTC's.
    $kyiv = new DateTimeZone('Europe/Kyiv');
    $answered = new DateTimeImmutable('2026-07-27T23:00:00', $kyiv); // 20:00Z

    $result = $this->scheduler->schedule(progressAt(LearningState::New), Grade::Good, $answered, $kyiv);

    $due = $result->dueAt();
    expect($due?->setTimezone($kyiv)->format('Y-m-d H:i:s'))->toBe('2026-07-28 00:00:00')
        ->and($due?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-07-27 21:00:00');
});

it('keeps an intra-day (0-day) step at the exact moment, never floored', function () {
    $evening = new DateTimeImmutable('2026-07-27T19:00:00Z');

    // new + again → learning, 0-day: "again this session", must stay exact (not next morning).
    $result = $this->scheduler->schedule(progressAt(LearningState::New), Grade::Again, $evening, new DateTimeZone('Europe/Kyiv'));

    expect($result->dueAt())->toEqual($evening);
});

it('counts the interval in calendar days across a clock change, not in exact hours', function () {
    // 23:30 on 2026-03-27, while Bucharest is still EET (+02:00); DST starts on the 29th.
    // learning + good = +4 days → 31 March, floored to 00:00 EEST = 2026-03-30T21:00Z.
    // Added as 96 exact hours in UTC instead, the 23:30 wall clock slides to 00:30 on 1 April and
    // the card is floored to the wrong calendar day — a whole day late, once a year.
    $bucharest = new DateTimeZone('Europe/Bucharest');
    $answered = new DateTimeImmutable('2026-03-27T23:30:00', $bucharest);

    $result = $this->scheduler->schedule(progressAt(LearningState::Learning, interval: 1), Grade::Good, $answered, $bucharest);

    $due = $result->dueAt();
    expect($due?->setTimezone($bucharest)->format('Y-m-d H:i:s'))->toBe('2026-03-31 00:00:00')
        ->and($due?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-03-30 21:00:00');
});

it('does not break an already-scheduled card when the user changes timezone', function () {
    // A card scheduled while on UTC, then re-graded after moving to Kyiv: the new due is simply the
    // start of the user's (new-zone) day — no crash, no drift into an invalid instant.
    $answered = new DateTimeImmutable('2026-07-27T08:00:00Z');

    $result = $this->scheduler->schedule(
        progressAt(LearningState::Learning, interval: 1), Grade::Good, $answered, new DateTimeZone('Europe/Kyiv'),
    );

    $due = $result->dueAt();
    // +4 days from 2026-07-27 → 07-31; floored to Kyiv-midnight = 2026-07-30T21:00:00Z.
    expect($due?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-07-30 21:00:00')
        ->and($due?->setTimezone(new DateTimeZone('Europe/Kyiv'))->format('Y-m-d H:i:s'))->toBe('2026-07-31 00:00:00');
});

it('counts a lapse and drops ease when a review card is forgotten', function () {
    $result = $this->scheduler->schedule(progressAt(LearningState::Review, ease: 2.50, interval: 40, lapses: 1), Grade::Again, $this->now);

    expect($result->lapses())->toBe(2)
        ->and($result->easeFactor())->toBe(2.30);
});

it('respects the ease floor of 1.30', function () {
    $result = $this->scheduler->schedule(progressAt(LearningState::Review, ease: 1.30, interval: 10), Grade::Hard, $this->now);

    expect($result->easeFactor())->toBe(1.30);
});

it('respects the interval ceiling of 365 days', function () {
    $result = $this->scheduler->schedule(progressAt(LearningState::Review, ease: 3.00, interval: 300), Grade::Good, $this->now);

    expect($result->intervalDays())->toBe(365);
});

it('advances the review counter on every grade', function () {
    $result = $this->scheduler->schedule(progressAt(LearningState::Review, interval: 10), Grade::Good, $this->now);

    expect($result->reps())->toBe(4); // started at 3
});

it('records the moment as last reviewed', function () {
    $result = $this->scheduler->schedule(progressAt(LearningState::New), Grade::Good, $this->now);

    expect($result->lastReviewedAt())->toEqual($this->now);
});

it('refuses to schedule a known term — that is the verification planner\'s job', function () {
    $this->scheduler->schedule(progressAt(LearningState::Known), Grade::Good, $this->now);
})->throws(LogicException::class);
