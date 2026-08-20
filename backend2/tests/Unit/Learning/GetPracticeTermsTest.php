<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Query\GetPracticeTerms;
use App\Modules\Learning\Application\Query\GetPracticeTermsHandler;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Doubles\InMemoryDueTermsReader;
use Tests\Doubles\InMemoryUserCollectionTermsReader;

function studiedView(string $id): DueTermView
{
    return new DueTermView(TermId::fromString($id), LearningState::Learning, 1, null, reps: 2);
}

/** A pool pair the learner has enrolled but never met. */
function enrolledNewView(string $id): DueTermView
{
    return new DueTermView(TermId::fromString($id), LearningState::New, 0, null, acquisition: Acquisition::New);
}

/**
 * `$pool` is what the learner has ENROLLED. `$byCollection` is what the collections hold — which an
 * UNSCOPED practice reads only to narrow the pool, and a COLLECTION-scoped one drills whole.
 * `$paused` is the third population: rows in a collection that are not enrolled («Убрать из
 * изучения»), which read as catalogue.
 *
 * @param  list<DueTermView>  $pool
 * @param  array<string, list<string>>  $byCollection
 * @param  list<DueTermView>  $paused
 */
function practiceHandler(array $pool = [], array $byCollection = [], array $paused = []): GetPracticeTermsHandler
{
    return new GetPracticeTermsHandler(
        new InMemoryDueTermsReader([], $pool, $paused),
        new InMemoryUserCollectionTermsReader([], $byCollection),
        new Randomizer(new Mt19937(1)), // seeded → deterministic shuffle
    );
}

beforeEach(function () {
    $this->user = UserId::generate();
});

it('drills the pool, each pair with its own progress', function () {
    $studiedId = TermId::generate()->value;
    $freshId = TermId::generate()->value;
    $handler = practiceHandler(pool: [studiedView($studiedId), enrolledNewView($freshId)]);

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20));

    expect($result)->toHaveCount(2);
    $byId = [];
    foreach ($result as $view) {
        $byId[$view->termId->value] = $view;
    }
    expect($byId[$studiedId]->state)->toBe(LearningState::Learning)
        ->and($byId[$studiedId]->reps)->toBe(2)
        ->and($byId[$freshId]->acquisition)->toBe(Acquisition::New)
        ->and($byId[$freshId]->reps)->toBe(0);
});

it('drills an UNTRIAGED collection — the topic, not the queue', function () {
    // «Зашёл в кафе, открыл тему, прошёл маленькую тренировку без разбора коллекции». Nothing is
    // enrolled and nothing has a progress row, which is exactly what a freshly generated topic
    // looks like; the selection used to be empty and the button a dead end.
    $ids = [TermId::generate()->value, TermId::generate()->value];
    $handler = practiceHandler(pool: [], byCollection: ['COL' => $ids]);

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20, collectionId: 'COL'));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toEqualCanonicalizing($ids);
    // …and every one of them is TAGGED as catalogue, which is what makes the assembler deal it the
    // easy trainers instead of a rung it has not earned.
    foreach ($result as $view) {
        expect($view->inPool)->toBeFalse()
            ->and($view->acquisition)->toBe(Acquisition::New);
    }
});

it('leads with the words being studied and fills the tail with the catalogue', function () {
    $studied = [TermId::generate()->value, TermId::generate()->value];
    $catalogue = [TermId::generate()->value, TermId::generate()->value];
    $handler = practiceHandler(
        pool: array_map('studiedView', $studied),
        byCollection: ['COL' => [...$catalogue, ...$studied]], // deliberately catalogue-first in the collection
    );

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20, collectionId: 'COL'));

    $flags = array_map(static fn (DueTermView $v): bool => $v->inPool, $result);
    expect($flags)->toBe([true, true, false, false]);
});

it('spends a session too small for the topic on the pool first', function () {
    $studied = TermId::generate()->value;
    $catalogue = [TermId::generate()->value, TermId::generate()->value];
    $handler = practiceHandler(
        pool: [studiedView($studied)],
        byCollection: ['COL' => [...$catalogue, $studied]],
    );

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 1, collectionId: 'COL'));

    expect($result)->toHaveCount(1)
        ->and($result[0]->termId->value)->toBe($studied);
});

it('reads a PAUSED word as catalogue — «Убрать из изучения» is not undone by a drill', function () {
    // It has a real row and a real history; what it does not have is enrolment. Drilling the topic
    // may reach it, but it must not be dealt as though it were still in the queue.
    $paused = TermId::generate()->value;
    $handler = practiceHandler(
        pool: [],
        byCollection: ['COL' => [$paused]],
        paused: [studiedView($paused)],
    );

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20, collectionId: 'COL'));

    expect($result)->toHaveCount(1)
        ->and($result[0]->termId->value)->toBe($paused)
        ->and($result[0]->inPool)->toBeFalse()
        // …and its own progress still rides along: the row is real, only the membership is not.
        ->and($result[0]->reps)->toBe(2);
});

it('never drills a word that is in a collection but not in the pool — WITHOUT a collection scope', function () {
    // The unscoped «свободная тренировка» has no topic the learner pointed at, so it has no honest
    // answer but the pool: everything they can reach is a catalogue of thousands.
    $handler = practiceHandler(pool: [], byCollection: ['COL' => [TermId::generate()->value]]);

    expect($handler(new GetPracticeTerms($this->user, sessionSize: 20)))->toBe([]);
});

it('ignores due_at and the daily quota entirely — a not-due studied term is included', function () {
    $studiedId = TermId::generate()->value;
    $handler = practiceHandler(pool: [studiedView($studiedId)]);

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20));

    expect($result)->toHaveCount(1)
        ->and($result[0]->termId->value)->toBe($studiedId);
});

it('caps the pool at the session size', function () {
    $ids = array_map(static fn (): string => TermId::generate()->value, range(1, 10));
    $handler = practiceHandler(pool: array_map('studiedView', $ids));

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 4));

    expect($result)->toHaveCount(4);
    // Every returned view is a real pool pair (the shuffle can't invent ids).
    $set = array_flip($ids);
    foreach ($result as $view) {
        expect($set)->toHaveKey($view->termId->value);
    }
});

it('narrows practice to one collection', function () {
    $inside = array_map(static fn (): string => TermId::generate()->value, range(1, 2));
    $outside = TermId::generate()->value;
    $handler = practiceHandler(
        pool: array_map('studiedView', [...$inside, $outside]),
        byCollection: ['COL' => $inside],
    );

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20, collectionId: 'COL'));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toEqualCanonicalizing($inside);
});

it('returns nothing when the pool is empty', function () {
    $handler = practiceHandler();

    expect($handler(new GetPracticeTerms($this->user)))->toBe([]);
});
