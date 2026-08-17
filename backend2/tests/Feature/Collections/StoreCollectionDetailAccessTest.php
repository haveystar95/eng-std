<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A published store collection (system, owner NULL) with one term. Returns [collectionId, termId].
 *
 * @return array{0: string, 1: string}
 */
function f25StoreDeck(): array
{
    $cid = Ulid::generate();
    $tid = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $cid, 'owner_id' => null, 'type' => 'system', 'title' => 'Airport', 'description' => null,
        'topic' => 'travel', 'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'public',
        'source' => 'curated', 'items_count' => 1, 'is_premium' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('terms')->insert([
        'id' => $tid, 'lang' => 'en', 'text' => 'passport', 'normalized_text' => 'passport', 'type' => 'word',
        'source' => 'curated', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $tid, 'lang' => 'ru', 'text' => 'паспорт', 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $cid, 'term_id' => $tid, 'position' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$cid, $tid];
}

function f25Subscribe(string $userId, string $collectionId, ?DateTimeInterface $unsubscribedAt = null): void
{
    DB::table('user_collections')->insert([
        'user_id' => $userId, 'collection_id' => $collectionId, 'added_at' => now(),
        'unsubscribed_at' => $unsubscribedAt, 'is_pinned' => false,
    ]);
}

/** @return array{0: User, 1: string} */
function f25Learner(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('device')->plainTextToken];
}

it('lets an active subscriber read a store collection detail (F25)', function () {
    [$user, $token] = f25Learner();
    [$cid, $tid] = f25StoreDeck();
    f25Subscribe($user->id, $cid);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$cid}")
        ->assertOk()
        ->assertJsonPath('data.id', $cid)
        ->assertJsonPath('data.type', 'system')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.term_id', $tid);
});

it('404s the store collection detail once the subscription is tombstoned', function () {
    [$user, $token] = f25Learner();
    [$cid] = f25StoreDeck();
    f25Subscribe($user->id, $cid, unsubscribedAt: now()); // inactive

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$cid}")
        ->assertNotFound();
});

it('404s a store collection the user never subscribed to', function () {
    [, $token] = f25Learner();
    [$cid] = f25StoreDeck(); // no subscription

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$cid}")
        ->assertNotFound();
});

it('404s another user\'s private collection without a subscription (regression)', function () {
    [, $token] = f25Learner();
    $other = User::factory()->create();
    $cid = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $cid, 'owner_id' => $other->id, 'type' => 'custom', 'title' => 'Secret',
        'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'private', 'source' => 'user',
        'items_count' => 0, 'is_premium' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$cid}")
        ->assertNotFound();
});
