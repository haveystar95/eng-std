<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * One collection carrying spend from every purpose that can be attributed to it:
 * generation + realtime from their ledgers, enrichment + example_regen via the term, images and
 * recap from the request log (they have no ledger of their own).
 *
 * @return array{0: string, 1: string, 2: string}  [adminToken, collectionId, termId]
 */
function costFixture(): array
{
    $user = User::factory()->create(['email' => 'spender@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'premium']);
    [$collectionId, $termId] = adminSeedTerm($user, 'Banking', 'account', 'счёт');

    DB::table('generation_requests')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'prompt' => 'иду в банк',
        'normalized_prompt' => 'иду в банк', 'source_lang' => 'ru', 'target_lang' => 'en',
        'levels' => json_encode(['A2']), 'size' => 10, 'prompt_version' => 'v5', 'status' => 'succeeded',
        'model' => 'gpt-4o', 'tokens_in' => 2000, 'tokens_out' => 1000, 'cost_usd' => '0.015000',
        'collection_id' => $collectionId, 'created_at' => now(),
    ]);

    DB::table('practice_dialogs')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'collection_id' => $collectionId,
        'status' => 'finished', 'lesson_json' => json_encode(['topic' => 'bank']),
        'expires_at' => now()->addHour(), 'tokens_in' => 300, 'tokens_out' => 150,
        'cost_usd' => '0.040000', 'finished_at' => now(), 'created_at' => now(),
    ]);

    DB::table('term_enrichments')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'model' => 'gpt-4o-mini',
        'tokens_in' => 800, 'tokens_out' => 200, 'cost_usd' => '0.000240', 'created_at' => now(),
    ]);

    DB::table('example_regenerations')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $termId, 'model' => 'gpt-4o-mini',
        'tokens_in' => 100, 'tokens_out' => 50, 'cost_usd' => '0.000045', 'created_at' => now(),
    ]);

    // Pexels: a real call, no usage block, no money — but it must still show up as a call.
    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'outbound', 'method' => 'GET',
        'host' => 'api.pexels.com', 'path' => '/v1/search', 'service' => 'pexels',
        'purpose' => 'images', 'collection_id' => $collectionId, 'status' => 200,
        'response_body' => json_encode(['photos' => []]), 'occurred_at' => now(),
    ]);

    // Recap: spend that lands in NO ledger, so the log is the only record of it.
    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'outbound', 'method' => 'POST',
        'host' => 'api.openai.com', 'path' => '/v1/chat/completions', 'service' => 'openai',
        'purpose' => 'recap', 'collection_id' => $collectionId, 'status' => 200,
        'response_body' => json_encode([
            'model' => 'gpt-4o-mini',
            'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 1000],
        ]),
        'occurred_at' => now(),
    ]);

    [, $adminToken] = adminActor();

    return [$adminToken, $collectionId, $termId];
}

it('breaks one collection down by purpose, in dollars and tokens', function () {
    [$adminToken, $collectionId] = costFixture();

    $body = test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/collections/{$collectionId}/costs")
        ->assertOk()
        ->json();

    $byPurpose = collect($body['by_purpose'])->keyBy('purpose');

    expect($byPurpose['generation']['cost_usd'])->toBe(0.015)
        ->and($byPurpose['generation']['tokens_in'])->toBe(2000)
        ->and($byPurpose['realtime']['cost_usd'])->toBe(0.04)
        ->and($byPurpose['enrichment']['cost_usd'])->toBe(0.00024)
        ->and($byPurpose['example_regen']['cost_usd'])->toBe(0.000045)
        // Pexels is free: a call, no cost. Counting the call is the point.
        ->and($byPurpose['images']['calls'])->toBe(1)
        ->and((float) $byPurpose['images']['cost_usd'])->toBe(0.0)
        // Recap priced from the log: 2000/1000*0.00015 + 1000/1000*0.0006.
        ->and($byPurpose['recap']['cost_usd'])->toBe(0.0009)
        ->and($body['total_usd'])->toBe(round(0.015 + 0.04 + 0.00024 + 0.000045 + 0.0009, 6))
        // The shared-term caveat is stated, not hidden.
        ->and($body['note'])->toContain('shared');
});

it('404s the cost of a collection that does not exist', function () {
    [$adminToken] = costFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/collections/' . Ulid::generate() . '/costs')
        ->assertNotFound();
});

it('summarises fleet spend by purpose over a period', function () {
    [$adminToken] = costFixture();

    $body = test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/costs?period=week')
        ->assertOk()
        ->assertJsonPath('period', 'week')
        ->json();

    $byPurpose = collect($body['by_purpose'])->keyBy('purpose');

    expect($byPurpose['generation']['cost_usd'])->toBe(0.015)
        ->and($byPurpose['enrichment']['cost_usd'])->toBe(0.00024)
        ->and($body['total_usd'])->toBeGreaterThan(0.05);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/costs?period=nonsense')
        ->assertStatus(422);
});

it('shows a cost column and the owner email in the collections list', function () {
    [$adminToken, $collectionId] = costFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/collections')
        ->assertOk()
        ->assertJsonPath('data.0.id', $collectionId)
        ->assertJsonPath('data.0.owner_email', 'spender@wt.test')
        // The list column carries the directly-attributable spend only.
        ->assertJsonPath('data.0.cost_usd', 0.055);
});

it('puts active users and the last failed outbound calls on the dashboard', function () {
    [$adminToken] = costFixture();

    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'outbound', 'method' => 'POST',
        'host' => 'api.openai.com', 'path' => '/v1/chat/completions', 'service' => 'openai',
        'purpose' => 'generation', 'status' => 503, 'occurred_at' => now(),
    ]);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/dashboard')
        ->assertOk()
        ->assertJsonPath('recent_failures.0.status', 503)
        ->assertJsonPath('recent_failures.0.purpose', 'generation')
        // No reviews in this fixture — nobody has been active.
        ->assertJsonPath('totals.active_users_7d', 0);
});
