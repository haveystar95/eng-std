<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\TriageTerms;
use App\Modules\Learning\Application\Command\TriageTermsHandler;
use App\Modules\Learning\Application\Dto\TriageInput;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\TriageId;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
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
        new ImmediateTransactionManager(),
        new FixedClock($ctx->now),
    );
}

function swipe(TermId $term, TriageVerdict $verdict, DateTimeImmutable $at): TriageInput
{
    return new TriageInput(TriageId::generate(), $term, $verdict, $at);
}

it('projects known to a known progress row', function () {
    $term = TermId::generate();
    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Known, $this->now)]));

    expect($this->progress->get($this->user, $term)?->state())->toBe(LearningState::Known);
});

it('projects unsure straight into learning, due now', function () {
    $term = TermId::generate();
    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unsure, $this->now)]));

    $p = $this->progress->get($this->user, $term);
    expect($p?->state())->toBe(LearningState::Learning)
        ->and($p?->dueAt())->toEqual($this->now);
});

it('leaves an unknown-swiped term new — no progress row', function () {
    $term = TermId::generate();
    $result = triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now)]));

    expect($result->accepted)->toBe(1)
        ->and($this->progress->count())->toBe(0);
});

it('returns a known term to new when swiped unknown, dropping its row', function () {
    $term = TermId::generate();
    $this->progress->save(TermProgress::knownFromTriage($this->user, $term));

    triageHandler($this)(new TriageTerms($this->user, [swipe($term, TriageVerdict::Unknown, $this->now)]));

    expect($this->progress->get($this->user, $term))->toBeNull();
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
        new ImmediateTransactionManager(), new FixedClock($this->now),
    );

    $result = $handler(new TriageTerms($this->user, [swipe(TermId::generate(), TriageVerdict::Known, $this->now)]));

    expect($result->unknown)->toBe(1)
        ->and($result->accepted)->toBe(0)
        ->and($this->triages->count())->toBe(0);
});
