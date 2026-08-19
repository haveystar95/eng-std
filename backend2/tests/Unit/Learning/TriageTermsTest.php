<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\TriageTerms;
use App\Modules\Learning\Application\Command\TriageTermsHandler;
use App\Modules\Learning\Application\Dto\TriageInput;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\TriageVerificationPlanner;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\TriageId;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FakeLearnerProfileReader;
use Tests\Doubles\FakeTermDifficultyReader;
use Tests\Doubles\FakeTermExistenceReader;
use Tests\Doubles\FixedClock;
use Tests\Doubles\ImmediateTransactionManager;
use Tests\Doubles\InMemoryTermProgressRepository;
use Tests\Doubles\InMemoryTriageRepository;

beforeEach(function () {
    $this->now = new DateTimeImmutable('2026-07-30T10:00:00Z');
    $this->user = UserId::generate();
    $this->triages = new InMemoryTriageRepository();
    $this->progress = new InMemoryTermProgressRepository();
});

function triageHandler(object $ctx): TriageTermsHandler
{
    return new TriageTermsHandler(
        $ctx->triages,
        $ctx->progress,
        FakeTermExistenceReader::knowingAll(),
        new TriageVerificationPlanner(),
        new FakeLearnerProfileReader(CefrLevel::B1),
        new FakeTermDifficultyReader(), // unknown difficulty → not risky → 90-day check
        new ImmediateTransactionManager(),
        new FixedClock($ctx->now),
    );
}

function swipe(TermId $term, TriageVerdict $verdict, DateTimeImmutable $at, int $seq = 1): TriageInput
{
    return new TriageInput(TriageId::generate(), $term, $verdict, $at, $seq);
}

it('projects known to a known progress row with a scheduled verification check', function () {
    $term = TermId::generate();
    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Known, $this->now)]));

    $p = $this->progress->get($this->user, $term);
    expect($p?->state())->toBe(LearningState::Known)
        // unknown difficulty → not risky → the 90-day check.
        ->and($p?->dueAt())->toEqual($this->now->modify('+90 days'))
        // …and it is OUTSIDE the acquisition ladder: a claim awaiting proof has no rung, so the
        // admission matrix does not apply to it and its check stays typing.
        ->and($p?->acquisition())->toBe(Acquisition::Graduated)
        ->and($p?->ladderStep())->toBeNull();
});

it('routes all three verdicts onto the ladder, and only «знаю» touches the scheduler', function () {
    // The whole triage contract in one table: the verdict decides a POSITION, not a schedule.
    $unknown = TermId::generate();
    $unsure = TermId::generate();
    $known = TermId::generate();

    triageHandler($this)(new TriageTerms($this->user, [
        swipe($unknown, TriageVerdict::Unknown, $this->now),
        swipe($unsure, TriageVerdict::Unsure, $this->now),
        swipe($known, TriageVerdict::Known, $this->now),
    ]));

    // «не знаю» → rung 0, IN THE POOL. The row exists so the enrolment has somewhere to live.
    $n = $this->progress->get($this->user, $unknown);
    expect($n?->acquisition())->toBe(Acquisition::New)
        ->and($n?->ladderStep())->toBe(0)
        ->and($n?->isEnrolled())->toBeTrue()
        ->and($n?->dueAt())->toBeNull();          // rung 0 schedules nothing either

    // «не уверен» → rung 1, also in the pool: past the intro, because the swipe pass already
    // showed them the word.
    $u = $this->progress->get($this->user, $unsure);
    expect($u?->acquisition())->toBe(Acquisition::Learning)
        ->and($u?->ladderStep())->toBe(1)
        ->and($u?->isEnrolled())->toBeTrue()
        ->and($u?->dueAt())->toBeNull();          // the ladder never schedules

    // «знаю» → off the ladder, out of the pool, and the ONE verdict that writes a scheduling field.
    $k = $this->progress->get($this->user, $known);
    expect($k?->ladderStep())->toBeNull()
        ->and($k?->state())->toBe(LearningState::Known)
        ->and($k?->isEnrolled())->toBeFalse()
        ->and($k?->dueAt())->not->toBeNull();     // its verification check
});

it('projects unsure straight into learning, due now', function () {
    $term = TermId::generate();
    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unsure, $this->now)]));

    $p = $this->progress->get($this->user, $term);
    // «Не уверен» is a POSITION on the acquisition ladder — first recognition rung, past the intro
    // — not a scheduler state. The scheduler is untouched: the pair is selected because it is
    // unfinished, not because it is due, so it has no due date at all.
    expect($p?->acquisition())->toBe(Acquisition::Learning)
        ->and($p?->ladderStep())->toBe(1)
        ->and($p?->state())->toBe(LearningState::New)
        ->and($p?->dueAt())->toBeNull();
});

it('enrols an unknown-swiped term at rung 0', function () {
    // «Не знаю» means «учи это». Until the pool it left no row at all and the word was found again
    // by "has a triage marker but no progress row" — a definition only readable backwards.
    $term = TermId::generate();
    $result = triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now)]));

    $p = $this->progress->get($this->user, $term);
    expect($result->accepted)->toBe(1)
        ->and($p?->acquisition())->toBe(Acquisition::New)
        ->and($p?->isEnrolled())->toBeTrue()
        ->and($p?->enrolledAt())->toEqual($this->now);
});

it('keeps the first enrolment moment when a word is swiped «не знаю» twice', function () {
    $term = TermId::generate();
    $handler = triageHandler($this);

    $handler(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now, seq: 1)]));
    $handler(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now, seq: 2)]));

    // Idempotent by design: «с какого дня я это учу» is not rewritten by a second swipe.
    expect($this->progress->get($this->user, $term)?->enrolledAt())->toEqual($this->now);
});

it('returns a known term to new when swiped unknown, keeping its history', function () {
    $term = TermId::generate();
    // A known term that carries history (e.g. later manually marked known from the word list):
    // the return must reset state but preserve reps/lapses, not blank the term out.
    $this->progress->save(TermProgress::reconstitute(
        $this->user, $term, LearningState::Known, 2.5, 0, null, reps: 12, lapses: 3, lastReviewedAt: $this->now,
    ));

    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now)]));

    $p = $this->progress->get($this->user, $term);
    expect($p?->state())->toBe(LearningState::New)
        ->and($p?->reps())->toBe(12)
        ->and($p?->lapses())->toBe(3)
        ->and($p?->dueAt())->toBeNull()
        // Back to rung 0, reps notwithstanding: a `known` mark was a claim, never a taught word,
        // so there is no recognition step it has ever passed.
        ->and($p?->acquisition())->toBe(Acquisition::New)
        ->and($p?->ladderStep())->toBe(0)
        // …and it joins the pool: the learner has just said they do not know it after all.
        ->and($p?->isEnrolled())->toBeTrue();
});

it('does not clobber real study progress with a stray unknown swipe', function () {
    $term = TermId::generate();
    $review = TermProgress::reconstitute(
        $this->user, $term, LearningState::Review, 2.5, 30, $this->now, reps: 5, lapses: 0, lastReviewedAt: $this->now,
    );
    $this->progress->save($review);

    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now)]));

    expect($this->progress->get($this->user, $term)?->state())->toBe(LearningState::Review);
});

it('picks the current verdict by client_seq, not decided_at, within a batch', function () {
    $term = TermId::generate();
    // Real order: known first, then the user returns it to learning (unknown). A lagging device
    // clock stamps the genuinely-later unknown with an EARLIER decided_at than the known.
    $known = swipe($term, TriageVerdict::Known, $this->now, seq: 1);
    $unknown = swipe($term, TriageVerdict::Unknown, $this->now->modify('-2 days'), seq: 2);

    triageHandler($this)(new TriageTerms($this->user, [$known, $unknown]));

    // Governing verdict is the higher seq (unknown) → rung 0, in the pool. Ordering by decided_at
    // would have let the older "known" win and wrongly parked the term as known.
    $p = $this->progress->get($this->user, $term);
    expect($p?->state())->toBe(LearningState::New)
        ->and($p?->isEnrolled())->toBeTrue();
});

it('keeps the higher-seq verdict when a lower-seq swipe arrives in a later batch (out-of-order chunks)', function () {
    $term = TermId::generate();
    $handler = triageHandler($this);

    // The genuinely-later "unknown" (seq 2) arrives FIRST — its chunk landed first...
    $handler(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now, seq: 2)]));
    // ...then the earlier "known" (seq 1) arrives in a later chunk.
    $handler(new TriageTerms($this->user, [swipe($term, TriageVerdict::Known, $this->now, seq: 1)]));

    // The re-projection reads the governing verdict across the whole log by client_seq, so the
    // stale "known" must NOT win. Arrival-order guards alone would have parked it known.
    $p = $this->progress->get($this->user, $term);
    expect($p?->state())->toBe(LearningState::New)
        ->and($p?->isEnrolled())->toBeTrue();
});

it('ignores a re-uploaded triage batch (idempotent by id)', function () {
    $term = TermId::generate();
    $input = swipe($term, TriageVerdict::Known, $this->now);
    $handler = triageHandler($this);

    $handler(new TriageTerms($this->user, [$input]));
    $second = $handler(new TriageTerms($this->user, [$input]));

    expect($second->accepted)->toBe(0)
        ->and($second->duplicates)->toBe(1)
        ->and($this->triages->count())->toBe(1);
});

it('skips unknown term ids', function () {
    $handler = new TriageTermsHandler(
        $this->triages, $this->progress,
        FakeTermExistenceReader::knowing([]), // nothing exists
        new TriageVerificationPlanner(),
        new FakeLearnerProfileReader(CefrLevel::B1),
        new FakeTermDifficultyReader(),
        new ImmediateTransactionManager(), new FixedClock($this->now),
    );

    $result = $handler(new TriageTerms($this->user, [swipe(TermId::generate(), TriageVerdict::Known, $this->now)]));

    expect($result->unknown)->toBe(1)
        ->and($result->accepted)->toBe(0)
        ->and($this->triages->count())->toBe(0);
});
