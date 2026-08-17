<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The half of admin curation that is easy to forget and impossible to fake: an edit that never
 * reaches the phone is not an edit, and a deletion that never reaches the phone leaves a ghost in
 * every offline mirror. Each case here syncs FIRST (so the device is up to date), curates, then
 * syncs again with `since` and asserts what came down the delta.
 *
 * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
 *         [userToken, adminToken, collectionId, termId, serverTime]
 */
function syncedDevice(): array
{
    $user = User::factory()->create(['email' => 'device@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    $userToken = $user->createToken('phone')->plainTextToken;

    [$collectionId, $termId] = adminSeedTerm($user, 'Banking', 'account', 'счёт');
    [, $adminToken] = adminActor();

    $serverTime = test()->withHeader('Authorization', "Bearer {$userToken}")
        ->getJson('/api/v1/sync')
        ->assertOk()
        ->json('data.server_time');

    return [$userToken, $adminToken, $collectionId, $termId, (string) $serverTime];
}

function deltaSince(string $userToken, string $since): array
{
    return test()->withHeader('Authorization', "Bearer {$userToken}")
        ->getJson('/api/v1/sync?since=' . urlencode($since))
        ->assertOk()
        ->json('data.changes');
}

it('carries an admin translation edit to an already-synced device', function () {
    [$userToken, $adminToken, , $termId, $since] = syncedDevice();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/terms/{$termId}", ['translation' => 'банковский счёт'])
        ->assertOk();

    $terms = deltaSince($userToken, $since)['terms'];

    expect($terms)->toHaveCount(1)
        ->and($terms[0]['id'])->toBe($termId)
        ->and($terms[0]['op'])->toBe('upsert')
        ->and($terms[0]['translation'])->toBe('банковский счёт');
});

it('carries an example edit too — the child table bumps the term', function () {
    [$userToken, $adminToken, , $termId, $since] = syncedDevice();

    $exampleId = (string) DB::table('term_examples')->where('term_id', $termId)->orderBy('id')->value('id');
    if ($exampleId === '') {
        $exampleId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();
        DB::table('term_examples')->insert([
            'id' => $exampleId, 'term_id' => $termId, 'sentence' => 'Old sentence.',
            'sentence_translation' => 'Старое.', 'source' => 'ai',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // That insert didn't move the term, so re-sync to put the device back in step.
        $since = test()->withHeader('Authorization', "Bearer {$userToken}")
            ->getJson('/api/v1/sync')->assertOk()->json('data.server_time');
    }

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/terms/{$termId}", [
            'example_id' => $exampleId,
            'example_sentence' => 'She closed her bank account.',
        ])
        ->assertOk();

    $terms = deltaSince($userToken, (string) $since)['terms'];

    expect($terms)->toHaveCount(1)
        ->and($terms[0]['example'])->toBe('She closed her bank account.');
});

it('sends a term TOMBSTONE when a term is retired', function () {
    [$userToken, $adminToken, , $termId, $since] = syncedDevice();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/terms/{$termId}")
        ->assertOk();

    $changes = deltaSince($userToken, $since);

    $term = collect($changes['terms'])->firstWhere('id', $termId);
    expect($term)->not->toBeNull()
        // Without this the word sits in the phone's local dictionary forever.
        ->and($term['op'])->toBe('delete');

    // And it leaves the deck, so the item is tombstoned too.
    $item = collect($changes['collection_items'])->firstWhere('term_id', $termId);
    expect($item['op'])->toBe('delete');
});

it('sends a collection TOMBSTONE to the owner when a collection is deleted', function () {
    [$userToken, $adminToken, $collectionId, , $since] = syncedDevice();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/collections/{$collectionId}", ['confirm_title' => 'Banking'])
        ->assertOk();

    $collections = deltaSince($userToken, $since)['collections'];

    $row = collect($collections)->firstWhere('id', $collectionId);
    expect($row)->not->toBeNull()->and($row['op'])->toBe('delete');
});

it('sends a collection TOMBSTONE to a subscriber too', function () {
    [, $adminToken, $collectionId] = syncedDevice();

    $subscriber = User::factory()->create(['email' => 'sub2@wt.test']);
    Profile::create(['user_id' => $subscriber->id, 'daily_goal' => 5, 'tier' => 'free']);
    $subToken = $subscriber->createToken('phone')->plainTextToken;
    DB::table('user_collections')->insert([
        'user_id' => $subscriber->id, 'collection_id' => $collectionId, 'added_at' => now(),
    ]);

    $since = test()->withHeader('Authorization', "Bearer {$subToken}")
        ->getJson('/api/v1/sync')->assertOk()->json('data.server_time');

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/collections/{$collectionId}", ['confirm_title' => 'Banking'])
        ->assertOk();

    $collections = deltaSince($subToken, (string) $since)['collections'];

    $row = collect($collections)->firstWhere('id', $collectionId);
    expect($row)->not->toBeNull()->and($row['op'])->toBe('delete');
});

it('keeps a full snapshot free of tombstones', function () {
    [$userToken, $adminToken, , $termId] = syncedDevice();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/terms/{$termId}")->assertOk();

    // since=null is a fresh client: it has nothing to delete, so it must be told about upserts only.
    $changes = test()->withHeader('Authorization', "Bearer {$userToken}")
        ->getJson('/api/v1/sync')->assertOk()->json('data.changes');

    expect(collect($changes['terms'])->where('op', 'delete'))->toBeEmpty()
        ->and(collect($changes['collections'])->where('op', 'delete'))->toBeEmpty();
});
