<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A learner with one collection, one term (with a pinned example carrying a distractor), and a
 * device token so the same fixture can check what /sync tells the phone afterwards.
 *
 * @return array{0: User, 1: string, 2: string, 3: string, 4: string}
 *         [user, userToken, adminToken, collectionId, termId]
 */
function curationFixture(): array
{
    $user = User::factory()->create(['email' => 'owner@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    $userToken = $user->createToken('phone')->plainTextToken;

    // enroll: false — these cases are about the CATALOGUE (what curating a term does to the
    // collections holding it), and several of them write the learner's progress row themselves.
    [$collectionId, $termId] = adminSeedTerm($user, 'Banking', 'account', 'счёт', enroll: false);

    $exampleId = Ulid::generate();
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => 'I opened a bank account.',
        'translation' => 'Я открыл счёт.', 'source' => 'ai',
    ]);
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $exampleId,
        'sentence' => 'I opened a bank account on the bank.', 'error_type' => 'preposition',
        'error_span' => 'on the bank', 'correction' => 'at the bank',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_enrichment_versions')->insert([
        'term_id' => $termId, 'generator_version' => 'enrich-v1', 'created_at' => now(),
    ]);

    [, $adminToken] = adminActor();

    return [$user, $userToken, $adminToken, $collectionId, $termId];
}

function pinnedExampleId(string $termId): string
{
    return (string) DB::table('term_examples')->where('term_id', $termId)->orderBy('id')->value('id');
}

it('states the blast radius of a term before anything is changed', function () {
    [, , $adminToken, , $termId] = curationFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/terms/{$termId}/impact")
        ->assertOk()
        ->assertJsonPath('text', 'account')
        ->assertJsonPath('collections_count', 1)
        ->assertJsonPath('users_with_progress', 0);
});

it('edits a term and audits the change', function () {
    [, , $adminToken, , $termId] = curationFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/terms/{$termId}", ['translation' => 'банковский счёт', 'ipa' => 'əˈkaʊnt'])
        ->assertOk()
        ->assertJsonPath('ipa', 'əˈkaʊnt')
        ->assertJsonPath('translations.0.text', 'банковский счёт');

    $audit = DB::table('admin_audit_log')->where('action', 'term.edit')->first();
    expect($audit)->not->toBeNull()
        ->and(json_decode((string) $audit->context, true)['translation'])->toBe('банковский счёт');
});

it('drops the distractors and re-queues enrichment when the example text changes', function () {
    [, , $adminToken, , $termId] = curationFixture();
    $exampleId = pinnedExampleId($termId);

    expect(DB::table('example_distractors')->where('example_id', $exampleId)->count())->toBe(1);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/terms/{$termId}", [
            'example_id' => $exampleId,
            'example_sentence' => 'She closed her bank account.',
            'example_translation' => 'Она закрыла свой счёт.',
        ])
        ->assertOk()
        ->assertJsonPath('examples.0.sentence', 'She closed her bank account.');

    // A distractor built from the OLD sentence would show the learner a "find the mistake" made of
    // text they are not looking at.
    expect(DB::table('example_distractors')->where('example_id', $exampleId)->count())->toBe(0)
        // Unmarked → the станок picks it up again on the next run.
        ->and(DB::table('term_enrichment_versions')->where('term_id', $termId)->count())->toBe(0);
});

it('rejects an empty term edit with 422', function () {
    [, , $adminToken, , $termId] = curationFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/terms/{$termId}", [])
        ->assertStatus(422);
});

it('retires a term: out of every collection, progress gone, review log untouched', function () {
    [$user, , $adminToken, $collectionId, $termId] = curationFixture();

    // Give the term a progress row and a review, the way real study would.
    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId, 'state' => 'learning',
        'ease_factor' => 2.5, 'interval_days' => 1, 'due_at' => now(), 'reps' => 1, 'lapses' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('reviews')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $termId,
        'exercise_mode' => 'typing', 'grade' => 'good', 'is_correct' => true, 'is_practice' => false,
        'client_seq' => 1, 'answered_at' => now(), 'created_at' => now(),
    ]);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('retired', true);

    expect(DB::table('terms')->where('id', $termId)->whereNotNull('deleted_at')->exists())->toBeTrue()
        ->and(DB::table('user_term_progress')->where('term_id', $termId)->count())->toBe(0)
        // The append-only log is history; retiring a term does not un-happen an answer.
        ->and(DB::table('reviews')->where('term_id', $termId)->count())->toBe(1)
        ->and(DB::table('collection_items')->where('term_id', $termId)->whereNull('deleted_at')->count())->toBe(0)
        ->and((int) DB::table('collections')->where('id', $collectionId)->value('items_count'))->toBe(0);

    // Gone from the list too.
    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/terms?search=account')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('edits a collection and curates its membership', function () {
    [$user, , $adminToken, $collectionId, $termId] = curationFixture();
    [, $otherTermId] = adminSeedTerm($user, 'Other deck', 'invoice', 'счёт-фактура', enroll: false);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/collections/{$collectionId}", ['title' => 'Банк и деньги'])
        ->assertOk()
        ->assertJsonPath('title', 'Банк и деньги');

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/admin/api/collections/{$collectionId}/terms", ['term_id' => $otherTermId])
        ->assertOk()
        ->assertJsonPath('items_count', 2);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/collections/{$collectionId}/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('items_count', 1);

    // Removing a term from a deck must not touch the dictionary entry itself.
    expect(DB::table('terms')->where('id', $termId)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('refuses to delete a collection unless the title is typed back', function () {
    [, , $adminToken, $collectionId] = curationFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/collections/{$collectionId}", ['confirm_title' => 'wrong'])
        ->assertStatus(422);

    expect(DB::table('collections')->where('id', $collectionId)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('deletes a collection for its owner AND every subscriber, clearing subscriptions', function () {
    [, , $adminToken, $collectionId] = curationFixture();

    $subscriber = User::factory()->create(['email' => 'sub@wt.test']);
    Profile::create(['user_id' => $subscriber->id, 'daily_goal' => 5, 'tier' => 'free']);
    DB::table('user_collections')->insert([
        'user_id' => $subscriber->id, 'collection_id' => $collectionId, 'added_at' => now(),
    ]);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/collections/{$collectionId}", ['confirm_title' => 'Banking'])
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(DB::table('collections')->where('id', $collectionId)->whereNotNull('deleted_at')->exists())->toBeTrue()
        // Stamped, not deleted: the stamp is what the subscriber's delta reads as a tombstone.
        ->and(DB::table('user_collections')
            ->where('collection_id', $collectionId)->whereNull('unsubscribed_at')->count())->toBe(0);

    $audit = DB::table('admin_audit_log')->where('action', 'collection.delete')->first();
    expect($audit)->not->toBeNull()
        ->and(json_decode((string) $audit->context, true)['subscribers'])->toBe(1);
});

it('404s curation of things that do not exist', function () {
    [, , $adminToken] = curationFixture();
    $ghost = Ulid::generate();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->patchJson("/admin/api/terms/{$ghost}", ['translation' => 'x'])->assertNotFound();
    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->deleteJson("/admin/api/terms/{$ghost}")->assertNotFound();
    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/collections/{$ghost}/impact")->assertNotFound();
});
