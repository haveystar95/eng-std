<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: string} the user and a bearer token */
function userWithToken(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('test-device')->plainTextToken];
}

/** Seed a collection owned by an arbitrary user (no HTTP). */
function seedCollection(User $owner, string $title = 'Owned'): string
{
    return app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        ownerId: UserId::fromString($owner->id),
        title: $title,
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
    ))->value;
}

it('lists an empty set with pagination meta', function () {
    [, $token] = userWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.has_more', false);
});

it('creates a custom collection and returns it', function () {
    [, $token] = userWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/collections', ['title' => 'Travel'])
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Travel')
        ->assertJsonPath('data.type', 'custom')
        ->assertJsonPath('data.source', 'user')
        ->assertJsonPath('data.items', []);

    $this->assertDatabaseHas('collections', ['title' => 'Travel', 'type' => 'custom', 'source' => 'user']);
});

it('is idempotent on a client-supplied id', function () {
    [, $token] = userWithToken();
    $id = Ulid::generate();
    $body = ['id' => $id, 'title' => 'Offline'];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/collections', $body)->assertStatus(201);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/collections', $body)->assertStatus(201);

    $this->assertDatabaseCount('collections', 1);
});

it('shows an owned collection', function () {
    [$user, $token] = userWithToken();
    $id = seedCollection($user, 'Mine');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.title', 'Mine');
});

it("hides another user's collection behind 404", function () {
    $owner = User::factory()->create();
    $id = seedCollection($owner);

    [, $token] = userWithToken();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$id}")
        ->assertNotFound();
});

it('updates the title of an owned collection', function () {
    [$user, $token] = userWithToken();
    $id = seedCollection($user, 'Old');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/collections/{$id}", ['title' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New');

    $this->assertDatabaseHas('collections', ['id' => $id, 'title' => 'New']);
});

it("refuses to edit another user's collection with 403", function () {
    $owner = User::factory()->create();
    $id = seedCollection($owner);

    [, $token] = userWithToken();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/collections/{$id}", ['title' => 'Hijack'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'collection_not_editable');
});

it('soft-deletes an owned collection', function () {
    [$user, $token] = userWithToken();
    $id = seedCollection($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$id}")
        ->assertNoContent();

    $this->assertSoftDeleted('collections', ['id' => $id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$id}")
        ->assertNotFound();
});

it('validates that a title is required', function () {
    [, $token] = userWithToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/collections', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/collections')->assertUnauthorized();
});
