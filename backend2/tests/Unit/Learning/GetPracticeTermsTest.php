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
 * `$pool` is what the learner has ENROLLED — the only thing practice draws from. `$candidates` is
 * what the collections hold, which practice reads only to NARROW the pool by collection.
 *
 * @param  list<DueTermView>  $pool
 * @param  array<string, list<string>>  $byCollection
 */
function practiceHandler(array $pool = [], array $byCollection = []): GetPracticeTermsHandler
{
    return new GetPracticeTermsHandler(
        new InMemoryDueTermsReader([], $pool),
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

it('never drills a word that is in a collection but not in the pool', function () {
    // Free practice is training, and a word nobody has decided to study is not in training. Opening
    // a 200-word collection used to drill all 200 of them.
    $handler = practiceHandler(pool: [], byCollection: ['COL' => [TermId::generate()->value]]);

    expect($handler(new GetPracticeTerms($this->user, sessionSize: 20, collectionId: 'COL')))->toBe([]);
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
