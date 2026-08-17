<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A user with a collection, a studied term (→ progress + a review), and one row in each spend/log
 * ledger, so every admin read endpoint has something to return.
 *
 * @return array{0: User, 1: string, 2: string, 3: string}  [user, adminToken, collectionId, termId]
 */
function adminFixture(): array
{
    $user = User::factory()->create(['email' => 'learner@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free', 'cefr_level' => 'B1']);
    $userToken = $user->createToken('phone')->plainTextToken;

    [$collectionId, $termId] = adminSeedTerm($user, 'Fruit', 'apple', 'яблоко');

    // Three answers, not one: the first two are the acquisition ladder's recognition steps, which
    // reach no scheduler. The third is what actually schedules the term into `learning`.
    test()->withHeader('Authorization', "Bearer {$userToken}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => array_map(static fn (int $seq): array => [
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing',
            'response' => 'apple', 'answered_at' => now()->addSeconds($seq)->toIso8601String(), 'client_seq' => $seq,
        ], [1, 2, 3])])->assertOk();

    DB::table('generation_requests')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'prompt' => 'иду в банк',
        'normalized_prompt' => 'иду в банк', 'source_lang' => 'ru', 'target_lang' => 'en',
        'levels' => json_encode(['A2']), 'size' => 10, 'prompt_version' => 'v4', 'status' => 'succeeded',
        'model' => 'gpt-4o', 'tokens_in' => 1000, 'tokens_out' => 500, 'cost_usd' => '0.007500',
        'created_at' => now(),
    ]);

    $dialogId = Ulid::generate();
    DB::table('practice_dialogs')->insert([
        'id' => $dialogId, 'user_id' => $user->id, 'collection_id' => $collectionId, 'status' => 'finished',
        'lesson_json' => json_encode(['topic' => 'bank']), 'expires_at' => now()->addHour(),
        'tokens_in' => 200, 'tokens_out' => 100, 'cost_usd' => '0.030000', 'finished_at' => now(),
        'summary' => 'ok', 'created_at' => now(),
    ]);
    DB::table('practice_dialog_messages')->insert([
        'id' => Ulid::generate(), 'dialog_id' => $dialogId, 'role' => 'assistant',
        'text' => 'Hello!', 'ts' => 1000, 'created_at' => now(),
    ]);

    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'inbound', 'method' => 'GET', 'path' => '/api/v1/stats',
        'status' => 200, 'user_id' => $user->id, 'occurred_at' => now(),
    ]);

    [, $adminToken] = adminActor();

    return [$user, $adminToken, $collectionId, $termId];
}

it('returns the dashboard with totals and cost breakdown', function () {
    [$user, $adminToken] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/dashboard')
        ->assertOk()
        ->assertJsonPath('totals.users', fn ($n) => $n >= 1)
        ->assertJsonPath('totals.collections', 1)
        ->assertJsonPath('totals.terms', 1)
        ->assertJsonPath('costs.all_time.generation', 0.0075)
        ->assertJsonPath('costs.all_time.practice', 0.03)
        ->assertJsonStructure(['costs' => ['today', 'last_7d', 'all_time' => ['generation', 'practice', 'enrichment', 'example_regen', 'total']]]);
});

it('lists users and filters by email', function () {
    [$user, $adminToken] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users?search=learner')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.email', 'learner@wt.test')
        ->assertJsonPath('data.0.progress_count', 1);
});

it('returns a user detail with progress, counters and costs', function () {
    [$user, $adminToken] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('email', 'learner@wt.test')
        ->assertJsonPath('tier', 'free')
        ->assertJsonPath('progress.total', 1)
        ->assertJsonPath('reviews_today', 3) // the fixture's two ladder steps plus one SRS review

        ->assertJsonPath('costs.generation.cost_usd', 0.0075)
        ->assertJsonPath('collections.0.title', 'Fruit');
});

it('returns a user\'s collections with per-collection progress counters', function () {
    [$user, $adminToken, $collectionId] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/collections")
        ->assertOk()
        ->assertJsonPath('data.0.id', $collectionId)
        ->assertJsonPath('data.0.title', 'Fruit')
        ->assertJsonPath('data.0.progress.total', 1)
        ->assertJsonPath('data.0.progress.in_progress', 1)   // apple studied → learning, not mastered
        ->assertJsonPath('data.0.progress.mastered', 0);
});

it('404s a collections tab for an unknown user', function () {
    [, $adminToken] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users/' . Ulid::generate() . '/collections')
        ->assertNotFound();
});

it('returns a user review feed', function () {
    [$user, $adminToken, , $termId] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.total', 3) // the fixture's two ladder steps plus one SRS review
        ->assertJsonPath('data.0.term_id', $termId)
        ->assertJsonPath('data.0.term_text', 'apple')
        ->assertJsonPath('data.0.exercise_mode', 'typing');
});

it('lists collections and returns a collection detail with terms', function () {
    [, $adminToken, $collectionId, $termId] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/collections?type=custom')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.type', 'custom');

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/collections/{$collectionId}")
        ->assertOk()
        ->assertJsonPath('id', $collectionId)
        ->assertJsonPath('terms.0.term_id', $termId)
        ->assertJsonPath('terms.0.translation', 'яблоко');
});

it('lists terms and returns a term detail with footprint', function () {
    [, $adminToken, , $termId] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/terms?search=apple')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $termId);

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('id', $termId)
        ->assertJsonPath('progress_count', 1)
        ->assertJsonPath('collections.0.type', 'custom');
});

it('lists request logs and filters by user', function () {
    [$user, $adminToken] = adminFixture();

    // Filter by path too: the user's own /reviews/batch call is also logged by Observability, so
    // scope to the stats row we inserted.
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/request-logs?user_id={$user->id}&path=stats")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.path', '/api/v1/stats');
});

it('lists practice dialogs and returns a dialog transcript', function () {
    [$user, $adminToken] = adminFixture();

    $dialog = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/practice-dialogs?user_id={$user->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.cost_usd', 0.03)
        ->json('data.0');

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/practice-dialogs/{$dialog['id']}")
        ->assertOk()
        ->assertJsonPath('transcript.0.text', 'Hello!');
});

it('lists generations and filters by status', function () {
    [$user, $adminToken] = adminFixture();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/generations?user_id={$user->id}&status=succeeded")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.model', 'gpt-4o')
        ->assertJsonPath('data.0.cost_usd', 0.0075);
});
