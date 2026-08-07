<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Seed a published store collection (system/public/curated, ru→en) with one term + translation.
 *
 * @return array{0: string, 1: string} [collectionId, termId]
 */
function publishedDeck(string $title = 'Store Deck', ?DateTimeInterface $when = null): array
{
    $when ??= now();
    $cid = Ulid::generate();
    $tid = Ulid::generate();

    DB::table('collections')->insert([
        'id' => $cid, 'owner_id' => null, 'type' => 'system', 'title' => $title, 'description' => null,
        'topic' => 'travel', 'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'public',
        'source' => 'curated', 'items_count' => 1, 'is_premium' => false, 'created_at' => $when, 'updated_at' => $when,
    ]);
    DB::table('terms')->insert([
        'id' => $tid, 'lang' => 'en', 'text' => 'w' . $tid, 'normalized_text' => 'w' . $tid, 'type' => 'word',
        'source' => 'curated', 'cefr' => 'A2', 'created_at' => $when, 'updated_at' => $when,
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $tid, 'lang' => 'ru', 'text' => 'паспорт', 'is_primary' => true,
        'created_at' => $when, 'updated_at' => $when,
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $cid, 'term_id' => $tid, 'position' => 0,
        'created_at' => $when, 'updated_at' => $when,
    ]);

    return [$cid, $tid];
}

function activeSubscription(string $userId, string $collectionId, DateTimeInterface $addedAt): void
{
    DB::table('user_collections')->insert([
        'user_id' => $userId, 'collection_id' => $collectionId, 'added_at' => $addedAt,
        'unsubscribed_at' => null, 'is_pinned' => false,
    ]);
}

/** GET /sync as $token and return the `data` envelope. */
function syncFeed(object $ctx, string $token, string $query = ''): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync' . ($query !== '' ? "?{$query}" : ''))
        ->assertOk()
        ->json('data');
}

function syncLearner(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('device')->plainTextToken];
}

it('full snapshot includes a subscribed store collection with its items and terms', function () {
    [$user, $token] = syncLearner();
    [$cid, $tid] = publishedDeck('Airport');
    activeSubscription($user->id, $cid, now()); // even a retroactive (pre-first-sync) subscription

    $data = syncFeed($this, $token);

    expect(array_column($data['changes']['collections'], 'id'))->toContain($cid)
        ->and(array_column($data['changes']['collection_items'], 'term_id'))->toContain($tid)
        ->and(array_column($data['changes']['terms'], 'id'))->toContain($tid);

    $collection = collect($data['changes']['collections'])->firstWhere('id', $cid);
    expect($collection['op'])->toBe('upsert')
        ->and($collection['type'])->toBe('system');   // type=system ⇒ client renders it read-only/store

    $term = collect($data['changes']['terms'])->firstWhere('id', $tid);
    expect($term['op'])->toBe('upsert')
        ->and($term['translation'])->toBe('паспорт');  // arrives fully — offline-renderable
});

it('brings a collection into the delta when subscribed after since, with its terms', function () {
    [$user, $token] = syncLearner();
    [$cid, $tid] = publishedDeck('Hotel');
    // Push the store collection + term into the past, so ONLY the subscription can pull them in.
    DB::table('collections')->where('id', $cid)->update(['updated_at' => now()->subDay()]);
    DB::table('collection_items')->where('collection_id', $cid)->update(['updated_at' => now()->subDay()]);
    DB::table('terms')->where('id', $tid)->update(['updated_at' => now()->subDay()]);

    $first = syncFeed($this, $token); // caught up, nothing subscribed yet
    $t1 = $first['server_time'];
    expect(array_column($first['changes']['collections'], 'id'))->not->toContain($cid);

    activeSubscription($user->id, $cid, now()); // subscribe AFTER since

    $data = syncFeed($this, $token, 'since=' . urlencode($t1));

    expect(array_column($data['changes']['collections'], 'id'))->toContain($cid)
        ->and(array_column($data['changes']['collection_items'], 'term_id'))->toContain($tid)
        ->and(array_column($data['changes']['terms'], 'id'))->toContain($tid); // shipped despite an old term updated_at
});

it('ships a per-user collection tombstone when the user unsubscribes', function () {
    [$user, $token] = syncLearner();
    [$cid] = publishedDeck('Metro');
    activeSubscription($user->id, $cid, now()->subMinute());

    $snapshot = syncFeed($this, $token); // snapshot already has it as an upsert
    $t1 = $snapshot['server_time'];
    expect(array_column($snapshot['changes']['collections'], 'id'))->toContain($cid);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/store/collections/{$cid}/subscribe")
        ->assertOk();

    $data = syncFeed($this, $token, 'since=' . urlencode($t1));
    $row = collect($data['changes']['collections'])->firstWhere('id', $cid);

    expect($row)->not->toBeNull()
        ->and($row['op'])->toBe('delete');
});

it('delivers store-collection edits to existing subscribers in the delta', function () {
    [$user, $token] = syncLearner();
    [$cid] = publishedDeck('Bank', now()->subDay());
    activeSubscription($user->id, $cid, now()->subDay()); // subscribed long ago

    $t1 = syncFeed($this, $token)['server_time'];

    // A curation edit (as store:publish / a title fix would do) bumps the collection.
    DB::table('collections')->where('id', $cid)->update(['title' => 'Bank (edited)', 'updated_at' => now()]);

    $data = syncFeed($this, $token, 'since=' . urlencode($t1));
    $row = collect($data['changes']['collections'])->firstWhere('id', $cid);

    expect($row)->not->toBeNull()
        ->and($row['op'])->toBe('upsert')
        ->and($row['title'])->toBe('Bank (edited)');
});

it('excludes a public store collection the user has not subscribed to', function () {
    [, $token] = syncLearner();
    [$cid, $tid] = publishedDeck('Not mine');

    $data = syncFeed($this, $token); // full snapshot

    expect(array_column($data['changes']['collections'], 'id'))->not->toContain($cid)
        ->and(array_column($data['changes']['collection_items'], 'term_id'))->not->toContain($tid)
        ->and(array_column($data['changes']['terms'], 'id'))->not->toContain($tid);
});
