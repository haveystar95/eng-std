<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\EnsureDefaultCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * User folders: «Сохранённые» (lazy, renameable, undeletable) and moving a word between two of the
 * learner's own folders.
 *
 * The rule these tests exist to hold is the one that is easy to break by accident: a folder is a
 * SHELF, not the pool. Deleting one, or moving a word out of one, must leave `enrolled_at` and the
 * review log exactly as they were — the only way out of the pool is «убрать из тренировки».
 */
it('creates «Сохранённые» lazily, once, and marks it default', function () {
    [$user, $token] = learner();

    expect(DB::table('collections')->where('owner_id', $user->id)->count())->toBe(0);

    $first = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections/default')
        ->assertOk()
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('data.type', 'custom')
        ->assertJsonPath('data.title', EnsureDefaultCollectionHandler::TITLE)
        ->json('data.id');

    $second = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections/default')->assertOk()->json('data.id');

    expect($second)->toBe($first);
    expect(DB::table('collections')->where('owner_id', $user->id)->where('is_default', true)->count())->toBe(1);
});

it('lets the default folder be renamed but never deleted', function () {
    [, $token] = learner();
    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections/default')->json('data.id');

    // Renaming is ordinary editing — the flag is the destination, the title is only the label.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/collections/{$id}", ['title' => 'Мои находки'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Мои находки')
        ->assertJsonPath('data.is_default', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'collection_not_deletable');

    $this->assertDatabaseHas('collections', ['id' => $id, 'deleted_at' => null]);
});

it('deletes an ordinary folder without touching the pool or the review log', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'ledger', 'книга учёта');
    answerTimes($this, $token, $termId, 'ledger', 3);

    $reviewsBefore = DB::table('reviews')->where('term_id', $termId)->count();
    expect($reviewsBefore)->toBeGreaterThan(0);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$collectionId}")
        ->assertNoContent();

    // The shelf is gone; the word is still enrolled and every answer it ever gave is still there.
    expect(DB::table('reviews')->where('term_id', $termId)->count())->toBe($reviewsBefore);
    $this->assertDatabaseMissing('user_term_progress', ['term_id' => $termId, 'enrolled_at' => null]);
    expect(DB::table('user_term_progress')->where('term_id', $termId)->whereNotNull('enrolled_at')->count())->toBe(1);
});

it('moves a term between two of the user\'s own folders', function () {
    [$user, $token] = learner();
    [$from, $termId] = seedCollectionWith($user, 'invoice', 'счёт');
    $to = seedFolder($user, 'Работа');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$from}/items/{$termId}/move", ['to_collection_id' => $to])
        ->assertNoContent();

    expect(liveItemCount($from, $termId))->toBe(0)
        ->and(liveItemCount($to, $termId))->toBe(1);
    // Still in the pool: a move is a change of shelf, nothing else.
    expect(DB::table('user_term_progress')->where('term_id', $termId)->whereNotNull('enrolled_at')->count())->toBe(1);
});

it('is idempotent when the same move is replayed', function () {
    [$user, $token] = learner();
    [$from, $termId] = seedCollectionWith($user, 'receipt', 'чек');
    $to = seedFolder($user, 'Работа');

    $move = fn () => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$from}/items/{$termId}/move", ['to_collection_id' => $to]);

    $move()->assertNoContent();
    $move()->assertNoContent();

    expect(liveItemCount($to, $termId))->toBe(1)->and(liveItemCount($from, $termId))->toBe(0);
});

it("refuses to move a term into another user's folder", function () {
    [$user, $token] = learner();
    [$from, $termId] = seedCollectionWith($user, 'statement', 'выписка');
    $foreign = seedFolder(User::factory()->create(), 'Чужая');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$from}/items/{$termId}/move", ['to_collection_id' => $foreign])
        ->assertStatus(403)
        ->assertJsonPath('code', 'collection_not_editable');

    // Nothing moved and nothing was lost: the refusal happens before either end is written.
    expect(liveItemCount($from, $termId))->toBe(1)->and(liveItemCount($foreign, $termId))->toBe(0);
});

it('adds an existing term to a folder by id, without creating a second term', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'deposit', 'вклад');
    $folder = seedFolder($user, 'Банк');

    $termsBefore = DB::table('terms')->count();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$folder}/items", ['term_id' => $termId])
        ->assertCreated()
        ->assertJsonPath('data.items.0.term_id', $termId);

    expect(DB::table('terms')->count())->toBe($termsBefore);
});

it('refuses a request that names both a term id and a text', function () {
    [$user, $token] = learner();
    $folder = seedFolder($user, 'Банк');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$folder}/items", [
            'term_id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'text' => 'apple',
        ])
        ->assertStatus(422);
});

it('ships the default flag on the sync delta', function () {
    [, $token] = learner();
    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections/default')->json('data.id');

    $delta = sync($this, $token);
    $row = collect($delta['changes']['collections'])->firstWhere('id', $id);

    expect($row['is_default'])->toBeTrue();
});

/** An empty custom folder owned by this user (no HTTP). */
function seedFolder(User $owner, string $title): string
{
    return app(\App\Modules\Collections\Application\Command\CreateCustomCollectionHandler::class)(
        new \App\Modules\Collections\Application\Command\CreateCustomCollection(
            ownerId: \App\Modules\Shared\Domain\ValueObject\UserId::fromString($owner->id),
            title: $title,
            sourceLang: new \App\Modules\Shared\Domain\ValueObject\LanguageCode('ru'),
            targetLang: new \App\Modules\Shared\Domain\ValueObject\LanguageCode('en'),
        ),
    )->value;
}

/** Live (not tombstoned) rows for this (collection, term) pair. */
function liveItemCount(string $collectionId, string $termId): int
{
    return DB::table('collection_items')
        ->where('collection_id', $collectionId)
        ->where('term_id', $termId)
        ->whereNull('deleted_at')
        ->count();
}
