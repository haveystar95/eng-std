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
function f24Deck(string $text = 'passport', string $translation = 'паспорт'): array
{
    $cid = Ulid::generate();
    $tid = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $cid, 'owner_id' => null, 'type' => 'system', 'title' => 'Airport', 'description' => null,
        'topic' => 'travel', 'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'public',
        'source' => 'curated', 'items_count' => 1, 'is_premium' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('terms')->insert([
        'id' => $tid, 'lang' => 'en', 'text' => $text, 'normalized_text' => $text, 'type' => 'word',
        'source' => 'curated', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $tid, 'lang' => 'ru', 'text' => $translation, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $cid, 'term_id' => $tid, 'position' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$cid, $tid];
}

function f24Subscribe(string $userId, string $collectionId, ?DateTimeInterface $unsubscribedAt = null): void
{
    DB::table('user_collections')->insert([
        'user_id' => $userId, 'collection_id' => $collectionId, 'added_at' => now(),
        'unsubscribed_at' => $unsubscribedAt, 'is_pinned' => false,
    ]);
}

/** @return array{0: User, 1: string} */
function f24Learner(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('device')->plainTextToken];
}

// ── F24: free practice must work on a SUBSCRIBED store collection (owner NULL) ──────────

it('offers a subscribed store collection term as a practice card (F24)', function () {
    [$user, $token] = f24Learner();
    [$cid, $tid] = f24Deck();
    f24Subscribe($user->id, $cid);

    // Before the fix the practice pool scoped by owner_id only → the store term was excluded and
    // the session came back empty (the on-device symptom).
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $cid, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->toHaveCount(1);
    expect($cards[0]['term_id'])->toBe($tid);
    expect($cards[0]['answer'])->toBe('passport');
});

it('offers a subscribed store collection term as a normal study (new) card too', function () {
    [$user, $token] = f24Learner();
    [$cid, $tid] = f24Deck();
    f24Subscribe($user->id, $cid);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $cid])
        ->assertOk()
        ->json('data.cards');

    // A new term brings its whole recognition chain — introduced and answered in one session.
    expect($cards)->toHaveCount(2);
    expect(array_unique(array_column($cards, 'term_id')))->toBe([$tid]);
    expect(array_column($cards, 'exercise_mode'))->each->toBe('multiple_choice'); // both rungs are recognition
    expect(array_column($cards, 'ladder_step'))->toBe([1, 2]);
});

it('includes a subscribed store term in the all-collections practice pool', function () {
    [$user, $token] = f24Learner();
    [, $tid] = f24Deck();
    [$cid] = [DB::table('collection_items')->where('term_id', $tid)->value('collection_id')];
    f24Subscribe($user->id, (string) $cid);

    // No collection_id → practice across everything the user has (owned ∪ subscribed).
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect(array_column($cards, 'term_id'))->toContain($tid);
});

// ── Negative: an unsubscribe (tombstone) must close access ─────────────────────────────

it('drops a store collection from practice once the subscription is tombstoned', function () {
    [$user, $token] = f24Learner();
    [$cid] = f24Deck();
    f24Subscribe($user->id, $cid, unsubscribedAt: now()); // inactive from the start

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $cid, 'practice' => true])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

it('does not leak a store collection the user never subscribed to', function () {
    [$user, $token] = f24Learner();
    [$cid] = f24Deck(); // published, but NO subscription for this user

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $cid, 'practice' => true])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});
