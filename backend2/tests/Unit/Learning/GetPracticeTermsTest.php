<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Query\GetPracticeTerms;
use App\Modules\Learning\Application\Query\GetPracticeTermsHandler;
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

/**
 * @param  list<string>  $candidates
 * @param  list<DueTermView>  $studied  progress rows among the candidates (any state)
 * @param  array<string, list<string>>  $byCollection
 */
function practiceHandler(array $candidates = [], array $studied = [], array $byCollection = []): GetPracticeTermsHandler
{
    return new GetPracticeTermsHandler(
        new InMemoryDueTermsReader([], $studied),
        new InMemoryUserCollectionTermsReader($candidates, $byCollection),
        new Randomizer(new Mt19937(1)), // seeded → deterministic shuffle
    );
}

beforeEach(function () {
    $this->user = UserId::generate();
});

it('drills every scope term — studied ones keep their state, the rest are new (reps 0)', function () {
    $studiedId = TermId::generate()->value;
    $newA = TermId::generate()->value;
    $newB = TermId::generate()->value;
    $handler = practiceHandler(
        candidates: [$studiedId, $newA, $newB],
        studied: [studiedView($studiedId)],
    );

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20));

    expect($result)->toHaveCount(3);
    $byId = [];
    foreach ($result as $view) {
        $byId[$view->termId->value] = $view;
    }
    expect($byId[$studiedId]->state)->toBe(LearningState::Learning)
        ->and($byId[$studiedId]->reps)->toBe(2)
        ->and($byId[$newA]->state)->toBe(LearningState::New)
        ->and($byId[$newA]->reps)->toBe(0)
        ->and($byId[$newB]->state)->toBe(LearningState::New);
});

it('ignores due_at and the daily quota entirely — a not-due studied term is included', function () {
    // The studied term has no due filter applied here: allAmong returns it regardless of due_at.
    $studiedId = TermId::generate()->value;
    $handler = practiceHandler(candidates: [$studiedId], studied: [studiedView($studiedId)]);

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20));

    expect($result)->toHaveCount(1)
        ->and($result[0]->termId->value)->toBe($studiedId);
});

it('caps the pool at the session size', function () {
    $ids = array_map(static fn (): string => TermId::generate()->value, range(1, 10));
    $handler = practiceHandler(candidates: $ids);

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 4));

    expect($result)->toHaveCount(4);
    // Every returned view is a real candidate (the shuffle can't invent ids).
    $set = array_flip($ids);
    foreach ($result as $view) {
        expect($set)->toHaveKey($view->termId->value);
    }
});

it('scopes practice to one collection', function () {
    $inside = array_map(static fn (): string => TermId::generate()->value, range(1, 2));
    $outside = TermId::generate()->value;
    $handler = practiceHandler(
        candidates: [...$inside, $outside],
        byCollection: ['COL' => $inside],
    );

    $result = $handler(new GetPracticeTerms($this->user, sessionSize: 20, collectionId: 'COL'));

    expect(array_map(fn (DueTermView $v): string => $v->termId->value, $result))->toEqualCanonicalizing($inside);
});

it('returns nothing when the scope has no terms', function () {
    $handler = practiceHandler();

    expect($handler(new GetPracticeTerms($this->user)))->toBe([]);
});
