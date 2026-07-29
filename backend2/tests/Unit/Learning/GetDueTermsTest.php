<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Query\GetDueTerms;
use App\Modules\Learning\Application\Query\GetDueTermsHandler;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\InMemoryDueTermsReader;
use Tests\Doubles\InMemoryProgressExistenceReader;
use Tests\Doubles\InMemoryUserCollectionTermsReader;

/**
 * @return list<DueTermView>
 */
function dueViews(int $count): array
{
    $views = [];
    for ($i = 0; $i < $count; $i++) {
        $views[] = new DueTermView(TermId::generate(), LearningState::Review, 4, null);
    }

    return $views;
}

/** @return list<string> */
function newCandidates(int $count): array
{
    return array_map(static fn (): string => TermId::generate()->value, range(1, $count));
}

/**
 * @param list<string> $candidates
 * @param list<string> $started
 */
function dueHandler(InMemoryDueTermsReader $due, array $candidates = [], array $started = []): GetDueTermsHandler
{
    return new GetDueTermsHandler(
        $due,
        new InMemoryUserCollectionTermsReader($candidates),
        new InMemoryProgressExistenceReader($started),
    );
}

beforeEach(function () {
    $this->user = UserId::generate();
    $this->now = new DateTimeImmutable('2026-07-27T08:00:00Z');
});

it('fills leftover session slots with new terms, due first', function () {
    $result = dueHandler(new InMemoryDueTermsReader(dueViews(5)), candidates: newCandidates(10))(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10),
    );

    expect($result)->toHaveCount(15);
    expect($result[0]->state)->toBe(LearningState::Review);
    expect($result[14]->state)->toBe(LearningState::New);
});

it('shows no new terms when due cards already fill the session', function () {
    $result = dueHandler(new InMemoryDueTermsReader(dueViews(25)), candidates: newCandidates(10))(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10),
    );

    expect($result)->toHaveCount(20)
        ->and(array_filter($result, fn (DueTermView $v): bool => $v->state === LearningState::New))->toBe([]);
});

it('never exceeds the remaining daily new-term quota', function () {
    $result = dueHandler(new InMemoryDueTermsReader(dueViews(5)), candidates: newCandidates(10))(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 3),
    );

    expect($result)->toHaveCount(8); // 5 due + 3 new
});

it('skips collection terms that already have progress', function () {
    $ids = newCandidates(3);
    $result = dueHandler(new InMemoryDueTermsReader(), candidates: $ids, started: [$ids[1]])(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10),
    );

    expect($result)->toHaveCount(2);
    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe([$ids[0], $ids[2]]);
});

it('scopes due and new to a collection when collection_id is set', function () {
    $inCollection = newCandidates(2);
    $dueInside = new DueTermView(TermId::fromString($inCollection[0]), LearningState::Review, 4, null);
    $dueOutside = new DueTermView(TermId::generate(), LearningState::Review, 4, null);

    $handler = new GetDueTermsHandler(
        new InMemoryDueTermsReader([$dueInside, $dueOutside]),
        new InMemoryUserCollectionTermsReader([], ['COL' => $inCollection]),
        new InMemoryProgressExistenceReader([$inCollection[0]]), // [0] studied, [1] still new
    );

    $result = $handler(new GetDueTerms(
        $this->user, $this->now, sessionSize: 20, newTermsRemaining: 10, collectionId: 'COL',
    ));

    // Due limited to the collection (outside card dropped) + the one unstudied collection term.
    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe([$inCollection[0], $inCollection[1]]);
    expect($result[1]->state)->toBe(LearningState::New);
});

it('caps the session size at 100', function () {
    $reader = new InMemoryDueTermsReader(dueViews(150));

    $result = dueHandler($reader)(new GetDueTerms($this->user, $this->now, sessionSize: 500));

    expect($result)->toHaveCount(100)
        ->and($reader->dueLimits[0])->toBe(100);
});
