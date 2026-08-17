<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Query\GetDueTerms;
use App\Modules\Learning\Application\Query\GetDueTermsHandler;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\InMemoryDueTermsReader;
use Tests\Doubles\InMemoryProgressExistenceReader;
use Tests\Doubles\InMemoryUserCollectionTermsReader;

/** @return list<string> */
function termIds(int $count): array
{
    return array_map(static fn (): string => TermId::generate()->value, range(1, max(1, $count)));
}

/** A pair the scheduler owes a review: off the acquisition ladder, so it costs no quota. */
function dueView(string $id): DueTermView
{
    return new DueTermView(
        TermId::fromString($id), LearningState::Review, 4, null,
        acquisition: Acquisition::Graduated,
    );
}

/**
 * @param list<DueTermView> $due
 * @param list<string> $candidates  the user's current collection terms (global) or one collection
 * @param list<string> $started     which candidates already have progress
 * @param array<string, list<string>> $byCollection  per-collection candidates for scoped tests
 */
function dueHandler(array $due, array $candidates = [], array $started = [], array $byCollection = []): array
{
    $reader = new InMemoryDueTermsReader($due);

    return [
        new GetDueTermsHandler(
            $reader,
            new InMemoryUserCollectionTermsReader($candidates, $byCollection),
            new InMemoryProgressExistenceReader($started),
        ),
        $reader,
    ];
}

beforeEach(function () {
    $this->user = UserId::generate();
    $this->now = new DateTimeImmutable('2026-07-29T08:00:00Z');
});

it('fills leftover session slots with new terms, due first', function () {
    $dueIds = termIds(5);
    $newIds = termIds(10);
    [$handler] = dueHandler(array_map('dueView', $dueIds), candidates: [...$dueIds, ...$newIds], started: $dueIds);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect($result)->toHaveCount(15);
    expect($result[0]->state)->toBe(LearningState::Review);
    expect($result[14]->state)->toBe(LearningState::New);
});

it('shows no new terms when due cards already fill the session', function () {
    $dueIds = termIds(25);
    [$handler] = dueHandler(array_map('dueView', $dueIds), candidates: $dueIds, started: $dueIds);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect($result)->toHaveCount(20)
        ->and(array_filter($result, fn (DueTermView $v): bool => $v->state === LearningState::New))->toBe([]);
});

it('never exceeds the remaining daily new-term quota', function () {
    $dueIds = termIds(5);
    $newIds = termIds(10);
    [$handler] = dueHandler(array_map('dueView', $dueIds), candidates: [...$dueIds, ...$newIds], started: $dueIds);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 3));

    expect($result)->toHaveCount(8); // 5 due + 3 new
});

it('skips collection terms that already have progress', function () {
    $ids = termIds(3);
    [$handler] = dueHandler([], candidates: $ids, started: [$ids[1]]);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect($result)->toHaveCount(2);
    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe([$ids[0], $ids[2]]);
});

it('studies only terms still in the user’s collections', function () {
    // A due card whose term is no longer in any (non-deleted) collection is excluded.
    $inCollection = termIds(1);
    $orphan = TermId::generate()->value; // has progress but its collection was deleted
    [$handler] = dueHandler([dueView($inCollection[0]), dueView($orphan)], candidates: $inCollection, started: $inCollection);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe($inCollection);
});

it('scopes due and new to one collection when collection_id is set', function () {
    $inCollection = termIds(2);
    $dueInside = dueView($inCollection[0]);
    $dueOutside = dueView(TermId::generate()->value);
    [$handler] = dueHandler([$dueInside, $dueOutside], byCollection: ['COL' => $inCollection], started: [$inCollection[0]]);

    $result = $handler(new GetDueTerms(
        $this->user, $this->now, sessionSize: 20, newTermsRemaining: 10, collectionId: 'COL',
    ));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe([$inCollection[0], $inCollection[1]]);
    expect($result[1]->state)->toBe(LearningState::New);
});

it('returns nothing when the user has no collections', function () {
    [$handler] = dueHandler([dueView(TermId::generate()->value)]);

    expect($handler(new GetDueTerms($this->user, $this->now)))->toBe([]);
});

it('caps the session size at 100', function () {
    $dueIds = termIds(150);
    [$handler, $reader] = dueHandler(array_map('dueView', $dueIds), candidates: $dueIds, started: $dueIds);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 500));

    expect($result)->toHaveCount(100)
        ->and($reader->dueLimits[0])->toBe(100);
});
