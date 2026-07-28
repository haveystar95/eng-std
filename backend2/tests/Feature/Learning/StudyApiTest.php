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

it('requires authentication for stats', function () {
    $this->getJson('/api/v1/stats')->assertUnauthorized();
});
