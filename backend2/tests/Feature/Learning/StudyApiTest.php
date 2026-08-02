<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: string} */
function learner(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('test-device')->plainTextToken];
}

/** Create a collection + word for the user and return the term id (no HTTP). */
function seedWordFor(User $user, string $text = 'apple', string $translation = 'яблоко'): string
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Fruit', new LanguageCode('ru'), new LanguageCode('en'),
    ));

    return app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;
}

/**
 * Like {@see seedWordFor} but also returns the collection id.
 *
 * @return array{0: string, 1: string}  [collectionId, termId]
 */
function seedCollectionWith(User $user, string $text, string $translation = 'x'): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, $text, new LanguageCode('ru'), new LanguageCode('en'),
    ));
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;

    return [$collectionId->value, $termId];
}

it('submits reviews, creating progress and daily stats', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.unknown', 0);

    $this->assertDatabaseHas('user_term_progress', ['user_id' => $user->id, 'term_id' => $termId]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.total_terms', 1)
        ->assertJsonPath('data.reviews_today', 1);
});

it('ignores a re-uploaded review batch', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user);
    $batch = ['reviews' => [[
        'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
        'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
    ]]];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/reviews/batch', $batch)->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', $batch)
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.duplicates', 1);
});

it('reports unknown terms in a batch', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => Ulid::generate(), 'exercise_mode' => 'typing', 'response' => 'whatever',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.unknown', 1);
});

it('has no due cards before anything is studied', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonStructure(['data' => ['session_id', 'cards']])
        ->assertJsonPath('data.cards', []);
});

it('lists a due card once it is overdue, with hydrated content', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'withdraw cash', 'снять наличные');

    // Answered 5 days ago: new + good → learning (interval 1 day) → due ~4 days ago.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'withdraw cash',
            'answered_at' => now()->subDays(5)->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk();

    // Due, in learning state → word_bank; prompt is the translation, answer the target text.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonPath('data.cards.0.term_id', $termId)
        ->assertJsonPath('data.cards.0.exercise_mode', 'word_bank')
        ->assertJsonPath('data.cards.0.answer', 'withdraw cash')
        ->assertJsonPath('data.cards.0.prompt', 'снять наличные');
});

it('validates the review batch', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reviews');
});

it('offers unreviewed collection terms as new study cards', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    $bank = seedWordFor($user, 'bank', 'банк');

    // New terms → multiple_choice; cards carry the answer so the client can play offline.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data.cards')
        ->assertJsonPath('data.cards.0.exercise_mode', 'multiple_choice')
        ->json('data.cards');

    expect(array_column($cards, 'term_id'))->toEqualCanonicalizing([$apple, $bank]);
    expect(array_column($cards, 'answer'))->toEqualCanonicalizing(['apple', 'bank']);
});

it('drops a term from the new pool once it has progress', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    $bank = seedWordFor($user, 'bank', 'банк');

    // Study "apple" now → it becomes learning (due in the future), no longer new.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'typing', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonCount(1, 'data.cards')
        ->assertJsonPath('data.cards.0.term_id', $bank)
        ->assertJsonPath('data.cards.0.exercise_mode', 'multiple_choice');
});

it('scopes the due session to one collection', function () {
    [$user, $token] = learner();
    [$collectionA] = seedCollectionWith($user, 'apple');
    seedCollectionWith($user, 'bank'); // a second collection, must not leak in

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionA])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->toHaveCount(1);
    expect($cards[0]['answer'])->toBe('apple');
});

it('reports per-collection progress (learned once a term graduates)', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    // Two good answers in order: new → learning → review (learned).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple', 'answered_at' => now()->subDays(6)->toIso8601String(), 'client_seq' => 1],
            ['id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple', 'answered_at' => now()->subDays(5)->toIso8601String(), 'client_seq' => 2],
        ]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/progress')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.terms_total', 1)
        ->assertJsonPath('data.0.in_progress_count', 1) // review, interval 4 (< 21) → in progress
        ->assertJsonPath('data.0.mastered_count', 0);
});

it('caps new cards at the profile daily new-term quota', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 1]);
    seedWordFor($user, 'apple', 'яблоко');
    seedWordFor($user, 'bank', 'банк');

    // Two new terms are available, but the user's daily quota is 1.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonCount(1, 'data.cards');
});

it('introduces no new cards when the daily goal is zero', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 0]);
    seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

it('rejects an answer for a term outside the session composition', function () {
    [$user, $token] = learner();
    [$colA, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    [, $bank] = seedCollectionWith($user, 'bank', 'банк');

    // Session scoped to collection A → its fixed composition is [apple].
    $sessionId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->json('data.session_id');

    // bank is a real term but not in this session → rejected; apple is in it → accepted.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $bank, 'exercise_mode' => 'multiple_choice', 'response' => 'bank', 'answered_at' => now()->toIso8601String(), 'session_id' => $sessionId, 'client_seq' => 1],
            ['id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'multiple_choice', 'response' => 'apple', 'answered_at' => now()->toIso8601String(), 'session_id' => $sessionId, 'client_seq' => 2],
        ]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.unknown', 1);
});

it('records a free-practice answer: streak counts, but progress is untouched', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'is_practice' => true, 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    // Never scheduled → no progress row; but logged and counted toward the day's reviews (streak).
    $this->assertDatabaseMissing('user_term_progress', ['user_id' => $user->id, 'term_id' => $termId]);
    $this->assertDatabaseHas('reviews', ['term_id' => $termId, 'is_practice' => true]);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.reviews_today', 1)
        ->assertJsonPath('data.total_terms', 0);
});

it('does not double the daily new-term quota across two scoped sessions', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 1]);
    [$colA, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    [$colB] = seedCollectionWith($user, 'bank', 'банк');

    // Session A offers its one allowed new card; answering it spends the day's global quota.
    $sessionA = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->assertJsonCount(1, 'data.cards')
        ->json('data.session_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'multiple_choice', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'session_id' => $sessionA, 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    // A different collection, same day → no new cards: the quota is one per user, not per collection.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colB])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

it('requires authentication for stats', function () {
    $this->getJson('/api/v1/stats')->assertUnauthorized();
});
