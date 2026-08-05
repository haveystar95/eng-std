<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: string} the user and a bearer token */
function storeUser(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('test-device')->plainTextToken];
}

/** Seed a store collection (public or system) directly — the app has no endpoint that creates these. */
function seedStoreCollection(array $attrs = []): string
{
    $id = Ulid::generate();
    DB::table('collections')->insert(array_merge([
        'id' => $id,
        'owner_id' => null,
        'type' => 'system',
        'title' => 'Store deck',
        'description' => null,
        'topic' => 'travel',
        'source_lang' => 'ru',
        'target_lang' => 'en',
        'visibility' => 'public',
        'source' => 'curated',
        'items_count' => 5,
        'is_premium' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));

    return $id;
}

function setTier(User $user, string $tier): void
{
    DB::table('profiles')->updateOrInsert(
        ['user_id' => $user->id],
        ['id' => Ulid::generate(), 'tier' => $tier, 'updated_at' => now(), 'created_at' => now()],
    );
}

it('lists public/system collections for the language pair, ordered by topic, with flags', function () {
    [, $token] = storeUser();
    seedStoreCollection(['title' => 'Travel deck', 'topic' => 'travel']);
    seedStoreCollection(['title' => 'Food deck', 'topic' => 'food', 'is_premium' => true]);
    // Excluded: a private collection and a different language pair.
    seedStoreCollection(['title' => 'Hidden', 'visibility' => 'private', 'type' => 'custom']);
    seedStoreCollection(['title' => 'Wrong pair', 'target_lang' => 'de']);

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/store/collections?source_lang=ru&target_lang=en')
        ->assertOk()
        ->assertJsonPath('meta.has_more', false)
        ->json('data');

    expect($body)->toHaveCount(2)
        ->and(array_column($body, 'title'))->toBe(['Food deck', 'Travel deck']) // topic asc: food < travel
        ->and($body[0]['is_premium'])->toBeTrue()
        ->and($body[0]['is_subscribed'])->toBeFalse()
        ->and($body[1]['is_premium'])->toBeFalse();
});

it('subscribes a free collection and is idempotent on repeat', function () {
    [$user, $token] = storeUser();
    $id = seedStoreCollection();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertOk()
        ->assertJsonPath('data.subscribed', true);

    // Repeat → still 200, no duplicate row (PK user_id+collection_id).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertOk();

    expect(DB::table('user_collections')->where('user_id', $user->id)->where('collection_id', $id)->count())->toBe(1);
});

it('marks an already-subscribed collection in the listing', function () {
    [$user, $token] = storeUser();
    $id = seedStoreCollection();
    DB::table('user_collections')->insert([
        'user_id' => $user->id, 'collection_id' => $id, 'added_at' => now(), 'is_pinned' => false,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/store/collections')
        ->assertOk()
        ->assertJsonPath('data.0.is_subscribed', true);
});

it('blocks a free user from subscribing to a premium collection with 403 subscription_required', function () {
    [$user, $token] = storeUser();
    setTier($user, 'free');
    $id = seedStoreCollection(['is_premium' => true]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertStatus(403)
        ->assertJsonPath('code', 'subscription_required');

    expect(DB::table('user_collections')->where('collection_id', $id)->count())->toBe(0);
});

it('lets a premium user subscribe to a premium collection', function () {
    [$user, $token] = storeUser();
    setTier($user, 'premium');
    $id = seedStoreCollection(['is_premium' => true]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertOk();

    expect(DB::table('user_collections')->where('user_id', $user->id)->where('collection_id', $id)->count())->toBe(1);
});

it('unsubscribes and is idempotent when not subscribed', function () {
    [$user, $token] = storeUser();
    $id = seedStoreCollection();
    DB::table('user_collections')->insert([
        'user_id' => $user->id, 'collection_id' => $id, 'added_at' => now(), 'is_pinned' => false,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertOk()
        ->assertJsonPath('data.subscribed', false);

    expect(DB::table('user_collections')->where('collection_id', $id)->count())->toBe(0);

    // A second unsubscribe is a harmless no-op.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertOk();
});

it('hides a private collection behind 404 on subscribe', function () {
    [, $token] = storeUser();
    $id = seedStoreCollection(['visibility' => 'private', 'type' => 'custom', 'owner_id' => Ulid::generate()]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/store/collections/{$id}/subscribe")
        ->assertNotFound();
});
