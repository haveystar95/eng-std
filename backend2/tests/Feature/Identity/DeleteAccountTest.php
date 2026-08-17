<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedTerm(?string $createdBy): string
{
    $id = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $id, 'lang' => 'en', 'text' => 'bank ' . $id, 'normalized_text' => 'bank ' . $id,
        'type' => 'word', 'source' => 'user', 'created_by' => $createdBy,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/** Seed the full spread of a user's data across every module. */
function seedUserData(User $user, string $termId): string
{
    $uid = $user->id;
    DB::table('profiles')->insert(['id' => Ulid::generate(), 'user_id' => $uid, 'created_at' => now(), 'updated_at' => now()]);

    $collectionId = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $collectionId, 'owner_id' => $uid, 'type' => 'custom', 'title' => 'Mine',
        'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'private', 'source' => 'user',
        'items_count' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
        'position' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // A store subscription (to a system deck that must survive).
    $storeId = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $storeId, 'owner_id' => null, 'type' => 'system', 'title' => 'Store',
        'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'public', 'source' => 'curated',
        'items_count' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('user_collections')->insert(['user_id' => $uid, 'collection_id' => $storeId, 'added_at' => now(), 'is_pinned' => false]);

    DB::table('user_term_progress')->insert(['user_id' => $uid, 'term_id' => $termId, 'state' => 'review', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('reviews')->insert(['id' => Ulid::generate(), 'user_id' => $uid, 'term_id' => $termId, 'grade' => 'good', 'answered_at' => now()]);
    DB::table('term_triages')->insert(['id' => Ulid::generate(), 'user_id' => $uid, 'term_id' => $termId, 'verdict' => 'known', 'decided_at' => now()]);
    DB::table('daily_user_stats')->insert(['user_id' => $uid, 'date' => now()->toDateString(), 'reviews_count' => 1]);
    DB::table('study_sessions')->insert(['id' => Ulid::generate(), 'user_id' => $uid, 'is_practice' => false, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('generation_requests')->insert([
        'id' => Ulid::generate(), 'user_id' => $uid, 'prompt' => 'x', 'normalized_prompt' => 'x',
        'source_lang' => 'ru', 'target_lang' => 'en', 'levels' => json_encode(['A2']), 'size' => 8,
        'prompt_version' => 'v4', 'status' => 'succeeded', 'created_at' => now(),
    ]);

    // A finished realtime practice dialog + its transcript (PII) and an example regeneration — all
    // must be erased with the account (device-batch F23: they survived deletion).
    $dialogId = Ulid::generate();
    DB::table('practice_dialogs')->insert([
        'id' => $dialogId, 'user_id' => $uid, 'collection_id' => $collectionId, 'status' => 'finished',
        'lesson_json' => json_encode(['words' => []]), 'expires_at' => now()->addHour(), 'created_at' => now(),
    ]);
    DB::table('practice_dialog_messages')->insert([
        'id' => Ulid::generate(), 'dialog_id' => $dialogId, 'role' => 'user', 'text' => 'hi', 'ts' => 1, 'created_at' => now(),
    ]);
    DB::table('example_regenerations')->insert([
        'id' => Ulid::generate(), 'user_id' => $uid, 'term_id' => $termId, 'model' => 'gpt-4o', 'created_at' => now(),
    ]);
    return $storeId;
}

it('deletes the account and every trace of the user across all modules', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;
    $ownTerm = seedTerm($user->id);
    $storeId = seedUserData($user, $ownTerm);

    // A pre-existing audit-log row for the user (anonymised, not deleted, on erase).
    $logId = Ulid::generate();
    DB::table('api_request_logs')->insert([
        'id' => $logId, 'direction' => 'inbound', 'method' => 'GET', 'path' => '/x',
        'user_id' => $user->id, 'occurred_at' => now(),
    ]);

    // A second user whose data must survive untouched.
    $other = User::factory()->create();
    $otherTerm = seedTerm($other->id);
    DB::table('reviews')->insert(['id' => Ulid::generate(), 'user_id' => $other->id, 'term_id' => $otherTerm, 'grade' => 'good', 'answered_at' => now()]);
    $other->createToken('device');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/auth/me')
        ->assertNoContent();

    // The user and everything scoped to them is gone.
    expect(DB::table('users')->where('id', $user->id)->count())->toBe(0)
        ->and(DB::table('profiles')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('collections')->where('owner_id', $user->id)->count())->toBe(0)
        ->and(DB::table('collection_items')->where('term_id', $ownTerm)->count())->toBe(0) // cascaded with the collection
        ->and(DB::table('user_collections')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('user_term_progress')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('reviews')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('term_triages')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('daily_user_stats')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('study_sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('generation_requests')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('practice_dialogs')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('practice_dialog_messages')->count())->toBe(0) // transcript PII gone (F23)
        ->and(DB::table('example_regenerations')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0);

    // Global terms stay; only the authorship link is dropped.
    expect(DB::table('terms')->where('id', $ownTerm)->count())->toBe(1)
        ->and(DB::table('terms')->where('id', $ownTerm)->value('created_by'))->toBeNull();

    // The pre-existing audit-log row stays but is anonymised (user_id nulled, not deleted).
    expect(DB::table('api_request_logs')->where('id', $logId)->count())->toBe(1)
        ->and(DB::table('api_request_logs')->where('id', $logId)->value('user_id'))->toBeNull();

    // Nothing belonging to the other user (or the store) was touched.
    expect(DB::table('users')->where('id', $other->id)->count())->toBe(1)
        ->and(DB::table('reviews')->where('user_id', $other->id)->count())->toBe(1)
        ->and(DB::table('terms')->where('id', $otherTerm)->value('created_by'))->toBe($other->id)
        ->and(DB::table('collections')->where('id', $storeId)->count())->toBe(1)
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $other->id)->count())->toBe(1);
});

it('requires authentication to delete an account', function () {
    $this->deleteJson('/api/v1/auth/me')->assertUnauthorized();
});
