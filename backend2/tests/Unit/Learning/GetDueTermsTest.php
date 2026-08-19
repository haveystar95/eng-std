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
use Tests\Doubles\InMemoryUserCollectionTermsReader;

/** @return list<string> */
function termIds(int $count): array
{
    return array_map(static fn (): string => TermId::generate()->value, range(1, max(1, $count)));
}

/** A pool pair the scheduler owes a review: off the acquisition ladder, so it costs no quota. */
function dueView(string $id): DueTermView
{
    return new DueTermView(
        TermId::fromString($id), LearningState::Review, 4, null,
        acquisition: Acquisition::Graduated,
    );
}

/** A pool pair standing at rung 0 — enrolled, never shown. A first meeting, charged to the quota. */
function introView(string $id): DueTermView
{
    return new DueTermView(
        TermId::fromString($id), LearningState::New, 0, null,
        acquisition: Acquisition::New,
    );
}

/**
 * Everything in `$pool` is ENROLLED — the reader port only ever speaks about pool pairs. A word that
 * is in a collection but NOT in the pool is expressed by leaving it out of `$pool` entirely, which
 * is what the gate tests below do.
 *
 * @param list<DueTermView> $pool
 * @param array<string, list<string>> $byCollection  per-collection terms, for the scoped tests
 */
function dueHandler(array $pool, array $byCollection = []): array
{
    $reader = new InMemoryDueTermsReader($pool);

    return [
        new GetDueTermsHandler($reader, new InMemoryUserCollectionTermsReader([], $byCollection)),
        $reader,
    ];
}

beforeEach(function () {
    $this->user = UserId::generate();
    $this->now = new DateTimeImmutable('2026-07-29T08:00:00Z');
});

it('fills leftover session slots with first meetings, due first', function () {
    $dueIds = termIds(5);
    $newIds = termIds(10);
    [$handler] = dueHandler([...array_map('dueView', $dueIds), ...array_map('introView', $newIds)]);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect($result)->toHaveCount(15);
    expect($result[0]->state)->toBe(LearningState::Review);
    expect($result[14]->state)->toBe(LearningState::New);
});

it('shows no first meetings when due cards already fill the session', function () {
    $dueIds = termIds(25);
    [$handler] = dueHandler([...array_map('dueView', $dueIds), ...array_map('introView', termIds(5))]);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect($result)->toHaveCount(20)
        ->and(array_filter($result, fn (DueTermView $v): bool => $v->state === LearningState::New))->toBe([]);
});

it('never exceeds the remaining daily new-term quota', function () {
    $dueIds = termIds(5);
    $newIds = termIds(10);
    [$handler, $reader] = dueHandler([...array_map('dueView', $dueIds), ...array_map('introView', $newIds)]);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 3));

    expect($result)->toHaveCount(8);          // 5 due + 3 first meetings
    expect($reader->introLimits[0])->toBe(3); // …and the quota is the LIMIT, not a trim afterwards
});

it('reads first meetings under their own limit so a big pool cannot crowd out the repeats', function () {
    // The regression the split exists for. Rung-0 pairs sort ahead of everything (no due_at, and the
    // ordering is NULLS FIRST), so one capped query over a freshly triaged pool of a hundred words
    // came back as a hundred first meetings, was trimmed to the daily quota, and left every repeat
    // out of the session.
    $dueIds = termIds(6);
    [$handler] = dueHandler([...array_map('introView', termIds(100)), ...array_map('dueView', $dueIds)]);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 2));

    expect($result)->toHaveCount(8); // 6 repeats + 2 first meetings, not 2 first meetings alone
});

it('never deals a word that is in a collection but not in the pool', function () {
    // The gate the whole chapter is about: the catalogue is not the queue.
    [$handler] = dueHandler([], byCollection: ['COL' => termIds(3)]);

    expect($handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10)))->toBe([]);
});

it('studies the whole pool, including a pair whose collection is gone', function () {
    // Deliberate, and the opposite of the pre-pool rule. Enrolment is a decision about a WORD; a
    // collection is a catalogue you can put back on the shelf. Deleting one pauses nothing.
    $orphan = TermId::generate()->value;
    [$handler] = dueHandler([dueView($orphan)]);

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe([$orphan]);
});

it('narrows the pool to one collection when collection_id is set', function () {
    $inCollection = termIds(2);
    [$handler] = dueHandler(
        [dueView($inCollection[0]), introView($inCollection[1]), dueView(TermId::generate()->value)],
        byCollection: ['COL' => $inCollection],
    );

    $result = $handler(new GetDueTerms(
        $this->user, $this->now, sessionSize: 20, newTermsRemaining: 10, collectionId: 'COL',
    ));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toBe([$inCollection[0], $inCollection[1]]);
    expect($result[1]->state)->toBe(LearningState::New);
});

it('returns nothing when the scoped collection is not the user’s', function () {
    // termIdsForCollection answers with an empty list for a collection the user cannot study, and an
    // EMPTY scope must stay empty — never widen back to the whole pool.
    [$handler] = dueHandler([dueView(TermId::generate()->value)]);

    expect($handler(new GetDueTerms($this->user, $this->now, collectionId: 'SOMEONE-ELSES')))->toBe([]);
});

it('returns nothing when the pool is empty', function () {
    [$handler] = dueHandler([]);

    expect($handler(new GetDueTerms($this->user, $this->now)))->toBe([]);
});

it('caps the session size at 100', function () {
    $dueIds = termIds(150);
    [$handler, $reader] = dueHandler(array_map('dueView', $dueIds));

    $result = $handler(new GetDueTerms($this->user, $this->now, sessionSize: 500));

    expect($result)->toHaveCount(100)
        ->and($reader->dueLimits[0])->toBe(100);
});
