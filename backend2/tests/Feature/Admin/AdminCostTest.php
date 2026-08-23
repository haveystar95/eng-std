<?php

declare(strict_types=1);

use App\Modules\Admin\Application\Port\AdminCostReader;
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

// The станок's ledger was not written between 06.08 and 13.08.2026 — the pack path replaced the
// one-term path and dropped the spend write — so collections enriched in that window reported
// станок = 0 while 15 model calls per collection really happened. The log is the only record left,
// and reporting 0 for work that cost money is the one answer that must not be given.
it('prices enrichment from the request log when the ledger has nothing for the collection', function () {
    [$adminToken, $collectionId] = costFixture();
    DB::table('term_enrichments')->delete();          // the ledger gap, exactly as it is live

    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'outbound', 'method' => 'POST',
        'host' => 'api.openai.com', 'path' => '/v1/chat/completions', 'service' => 'openai',
        'purpose' => 'enrichment', 'collection_id' => $collectionId, 'status' => 200,
        'response_body' => json_encode([
            'model' => 'gpt-4o-mini',
            'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 100],
        ]),
        'occurred_at' => now(),
    ]);

    $body = test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/collections/{$collectionId}/costs")
        ->assertOk()
        ->json();

    $enrichment = collect($body['by_purpose'])->firstWhere('purpose', 'enrichment');

    // 3000/1000*0.00015 + 100/1000*0.0006
    expect($enrichment['calls'])->toBe(1)
        ->and($enrichment['cost_usd'])->toBe(0.00051)
        // …and the panel says which number it is looking at.
        ->and($body['note'])->toContain('по логу вызовов');
});

// The ledger is authoritative and path-independent (a console backfill stamps no collection on the
// log). When it has rows, the log must not be added on top of it.
it('prefers the ledger over the log and never sums both', function () {
    [$adminToken, $collectionId] = costFixture();

    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'outbound', 'method' => 'POST',
        'host' => 'api.openai.com', 'path' => '/v1/chat/completions', 'service' => 'openai',
        'purpose' => 'enrichment', 'collection_id' => $collectionId, 'status' => 200,
        'response_body' => json_encode([
            'model' => 'gpt-4o-mini',
            'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 100],
        ]),
        'occurred_at' => now(),
    ]);

    $body = test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/collections/{$collectionId}/costs")
        ->assertOk()
        ->json();

    $enrichment = collect($body['by_purpose'])->firstWhere('purpose', 'enrichment');

    expect($enrichment['cost_usd'])->toBe(0.00024)     // the ledger's number, not the sum
        ->and($body['note'])->not->toContain('по логу вызовов');
});

// ── QA-BUG-4: the per-user breakdown reported zero output tokens for everyone ────────────────────
//
// `COALESCE(SUM(tokens_out),0) AS toO` — an UNQUOTED identifier, which Postgres folds to lower
// case. The column came back as `too`, `$row->toO` was null, null cast to 0, and the money beside
// it was right, so nothing ever looked broken. The fixture's ledgers carry 1000 / 150 / 50 output
// tokens; the panel and `qa:cost` must both say so.

it('reports the output tokens a user actually spent (QA-BUG-4)', function () {
    [$adminToken] = costFixture();
    $userId = (string) DB::table('users')->where('email', 'spender@wt.test')->value('id');

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$userId}")
        ->assertOk()
        ->assertJsonPath('costs.generation.tokens_in', 2000)
        ->assertJsonPath('costs.generation.tokens_out', 1000)
        ->assertJsonPath('costs.practice.tokens_in', 300)
        ->assertJsonPath('costs.practice.tokens_out', 150)
        ->assertJsonPath('costs.example_regen.tokens_in', 100)
        ->assertJsonPath('costs.example_regen.tokens_out', 50);
});

it('reports them through the port qa:cost reads, windowed as well as all-time', function () {
    costFixture();
    $userId = (string) DB::table('users')->where('email', 'spender@wt.test')->value('id');

    $all = app(AdminCostReader::class)->userBreakdownSince($userId, null);
    $week = app(AdminCostReader::class)->userBreakdownSince($userId, new DateTimeImmutable('-7 days'));

    expect($all->generation->tokensOut)->toBe(1000)
        ->and($all->practice->tokensOut)->toBe(150)
        ->and($all->exampleRegen->tokensOut)->toBe(50)
        // The windowed branch builds the same select; the fixture's rows are all from today.
        ->and($week->generation->tokensOut)->toBe(1000)
        ->and($week->practice->tokensOut)->toBe(150)
        ->and($week->exampleRegen->tokensOut)->toBe(50);
});
