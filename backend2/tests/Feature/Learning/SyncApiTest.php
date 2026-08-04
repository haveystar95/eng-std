<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() are shared across the suite.

/** GET /sync and return the `data` envelope. */
function sync(object $ctx, string $token, string $query = ''): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync' . ($query !== '' ? "?{$query}" : ''))
        ->assertOk()
        ->json('data');
}

it('returns a full snapshot when since is omitted', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $data = sync($this, $token);

    expect($data['has_more'])->toBeFalse()
        ->and($data['server_time'])->not->toBeNull()
        ->and(array_column($data['changes']['collections'], 'id'))->toContain($col)
        ->and(array_column($data['changes']['collection_items'], 'term_id'))->toContain($money)
        ->and(array_column($data['changes']['terms'], 'id'))->toContain($money);

    // The metro view needs text + translation offline.
    $term = collect($data['changes']['terms'])->firstWhere('id', $money);
    expect($term['op'])->toBe('upsert')
        ->and($term['text'])->toBe('money')
        ->and($term['translation'])->toBe('деньги');

    // source/type ride along so the client can render the origin badge (ИИ / my / store).
    $collection = collect($data['changes']['collections'])->firstWhere('id', $col);
    expect($collection['source'])->toBe('user')
        ->and($collection['type'])->toBe('custom');
});

it('returns only changes after since', function () {
    [$user, $token] = learner();
    [$colA] = seedCollectionWith($user, 'apple', 'яблоко');
    // Push colA clearly before the cursor (a caught-up client already has it). `since` is inclusive
    // (second precision → the boundary second is re-sent), so separate the seconds deterministically.
    DB::table('collections')->where('id', $colA)->update(['updated_at' => now()->subDay()]);

    $t1 = sync($this, $token)['server_time'];

    [$colB] = seedCollectionWith($user, 'bank', 'банк');       // the change after t1
    $data = sync($this, $token, 'since=' . urlencode($t1));

    expect(array_column($data['changes']['collections'], 'id'))
        ->toContain($colB)
        ->not->toContain($colA);
});

it('returns an empty delta when the client is caught up', function () {
    [$user, $token] = learner();
    [$col, $term] = seedCollectionWith($user, 'apple', 'яблоко');
    // Everything is in the past relative to the cursor → nothing at/after `since`.
    DB::table('collections')->where('id', $col)->update(['updated_at' => now()->subDay()]);
    DB::table('collection_items')->where('collection_id', $col)->update(['updated_at' => now()->subDay()]);
    DB::table('terms')->where('id', $term)->update(['updated_at' => now()->subDay()]);

    $data = sync($this, $token, 'since=' . urlencode(now()->toIso8601String()));

    expect($data['has_more'])->toBeFalse()
        ->and($data['changes']['collections'])->toBe([])
        ->and($data['changes']['collection_items'])->toBe([])
        ->and($data['changes']['terms'])->toBe([])
        ->and($data['changes']['progress'])->toBe([]);
});

it('ships a tombstone when a collection is deleted', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко');
    $t1 = sync($this, $token)['server_time'];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$col}")->assertSuccessful();

    $data = sync($this, $token, 'since=' . urlencode($t1));
    $row = collect($data['changes']['collections'])->firstWhere('id', $col);
    expect($row)->not->toBeNull()->and($row['op'])->toBe('delete');
});

it('ships a tombstone when a term is removed from a collection', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    $t1 = sync($this, $token)['server_time'];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$col}/items/{$apple}")->assertSuccessful();

    $data = sync($this, $token, 'since=' . urlencode($t1));
    $row = collect($data['changes']['collection_items'])->firstWhere('term_id', $apple);
    expect($row)->not->toBeNull()->and($row['op'])->toBe('delete');
});

it('includes progress rows in the delta', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    // "unsure" triage → a learning progress row.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unsure',
            'decided_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk();

    $data = sync($this, $token);
    $p = collect($data['changes']['progress'])->firstWhere('term_id', $money);
    expect($p)->not->toBeNull()->and($p['op'])->toBe('upsert')->and($p['state'])->toBe('learning');
});

it('paginates with next_cursor until has_more is false', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'w0', 'п0');
    for ($i = 1; $i <= 5; $i++) {
        addWordTo($col, $user->id, "w{$i}", "п{$i}");        // 6 items + 6 terms + 1 collection = 13 changes
    }

    $collections = [];
    $items = [];
    $terms = [];
    $query = 'limit=3';
    $pages = 0;
    do {
        $data = sync($this, $token, $query);
        $collections = array_merge($collections, array_column($data['changes']['collections'], 'id'));
        $items = array_merge($items, array_column($data['changes']['collection_items'], 'term_id'));
        $terms = array_merge($terms, array_column($data['changes']['terms'], 'id'));
        $query = 'limit=3&cursor=' . urlencode((string) $data['next_cursor']);
        $pages++;
    } while ($data['has_more'] && $pages < 20);

    expect($data['has_more'])->toBeFalse()
        ->and($pages)->toBeGreaterThan(1)                    // actually paged
        ->and($collections)->toContain($col)
        ->and(count($terms))->toBe(6)
        ->and(count($items))->toBe(6);
});
