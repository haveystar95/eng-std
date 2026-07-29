<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
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
            'id' => Ulid::generate(), 'term_id' => $termId, 'grade' => 'good',
            'answered_at' => now()->toIso8601String(),
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
        'id' => Ulid::generate(), 'term_id' => $termId, 'grade' => 'good',
        'answered_at' => now()->toIso8601String(),
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
            'id' => Ulid::generate(), 'term_id' => Ulid::generate(), 'grade' => 'good',
            'answered_at' => now()->toIso8601String(),
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.unknown', 1);
});

it('has no due cards before anything is studied', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('lists a due card once it is overdue, with hydrated content', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'withdraw cash', 'снять наличные');

    // Answered 5 days ago: new + good → learning (interval 1 day) → due ~4 days ago.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'grade' => 'good',
            'answered_at' => now()->subDays(5)->toIso8601String(),
        ]]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due')
        ->assertOk()
        ->assertJsonPath('data.0.term_id', $termId)
        ->assertJsonPath('data.0.text', 'withdraw cash')
        ->assertJsonPath('data.0.translation', 'снять наличные');
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

    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.state', 'new')
        ->assertJsonPath('data.1.state', 'new')
        ->json('data');

    expect(array_column($data, 'term_id'))->toEqualCanonicalizing([$apple, $bank]);
    // New cards carry their content so the client can study offline.
    expect(array_column($data, 'text'))->toEqualCanonicalizing(['apple', 'bank']);
});

it('drops a term from the new pool once it has progress', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    $bank = seedWordFor($user, 'bank', 'банк');

    // Study "apple" now → it becomes learning (due in the future), no longer new.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $apple, 'grade' => 'good',
            'answered_at' => now()->toIso8601String(),
        ]]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.term_id', $bank)
        ->assertJsonPath('data.0.state', 'new');
});

it('scopes the due session to one collection', function () {
    [$user, $token] = learner();
    [$collectionA] = seedCollectionWith($user, 'apple');
    seedCollectionWith($user, 'bank'); // a second collection, must not leak in

    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due?collection_id=' . $collectionA)
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['text'])->toBe('apple');
});

it('reports per-collection progress (learned once a term graduates)', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    // Two good answers in order: new → learning → review (learned).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $termId, 'grade' => 'good', 'answered_at' => now()->subDays(6)->toIso8601String()],
            ['id' => Ulid::generate(), 'term_id' => $termId, 'grade' => 'good', 'answered_at' => now()->subDays(5)->toIso8601String()],
        ]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/progress')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.total', 1)
        ->assertJsonPath('data.0.learned', 1)
        ->assertJsonPath('data.0.mastered', 0);
});

it('requires authentication for stats', function () {
    $this->getJson('/api/v1/stats')->assertUnauthorized();
});
