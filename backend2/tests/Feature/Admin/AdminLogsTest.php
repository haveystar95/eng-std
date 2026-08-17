<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * One outbound OpenAI row shaped like the real thing (usage block included) plus one inbound row,
 * so the filters have something to separate.
 *
 * @return array{0: string, 1: string, 2: string}  [adminToken, outboundId, collectionId]
 */
function logFixture(): array
{
    $user = User::factory()->create(['email' => 'logs@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    [$collectionId] = adminSeedTerm($user, 'Bank', 'account', 'счёт');

    $outboundId = Ulid::generate();
    DB::table('api_request_logs')->insert([
        'id' => $outboundId,
        'direction' => 'outbound', 'method' => 'POST', 'host' => 'api.openai.com',
        'path' => '/v1/chat/completions', 'service' => 'openai',
        'purpose' => 'generation', 'collection_id' => $collectionId,
        'status' => 200, 'duration_ms' => 4210, 'user_id' => $user->id,
        'request_headers' => json_encode(['Authorization' => '[REDACTED]']),
        'request_body' => json_encode(['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'иду в банк']]]),
        'response_body' => json_encode([
            'model' => 'gpt-4o',
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
        ]),
        'occurred_at' => now(),
    ]);

    DB::table('api_request_logs')->insert([
        'id' => Ulid::generate(), 'direction' => 'inbound', 'method' => 'GET',
        'path' => '/api/v1/stats', 'status' => 200, 'user_id' => $user->id, 'occurred_at' => now(),
    ]);

    [, $adminToken] = adminActor();

    return [$adminToken, $outboundId, $collectionId];
}

it('derives model, tokens and cost for an outbound call from the stored bodies', function () {
    [$adminToken] = logFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs?direction=outbound')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.model', 'gpt-4o')
        ->assertJsonPath('data.0.tokens_in', 1000)
        ->assertJsonPath('data.0.tokens_out', 500)
        // 1000/1000*0.0025 + 500/1000*0.01 — the same table Generation prices its ledgers with.
        ->assertJsonPath('data.0.cost_usd', 0.0075)
        ->assertJsonPath('data.0.purpose', 'generation');
});

it('filters by direction, provider, purpose, collection and status class', function () {
    [$adminToken, , $collectionId] = logFixture();

    $get = fn (string $q) => test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs?' . $q)->assertOk()->json('meta.total');

    // Scoped to outbound: every admin call in this test is itself logged inbound by the
    // Observability middleware, so an unscoped inbound count grows as the test runs.
    expect($get('direction=outbound'))->toBe(1)
        ->and($get('direction=inbound&path=stats'))->toBe(1)
        ->and($get('provider=openai'))->toBe(1)
        ->and($get('purpose=generation'))->toBe(1)
        ->and($get('purpose=images'))->toBe(0)
        ->and($get('collection_id=' . $collectionId))->toBe(1)
        ->and($get('direction=outbound&status_class=2xx'))->toBe(1)
        ->and($get('direction=outbound&status_class=5xx'))->toBe(0)
        ->and($get('direction=outbound&status=200'))->toBe(1);
});

it('answers 422 — not 500 — for a filter value it cannot parse', function () {
    [$adminToken] = logFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs?from=not-a-date')
        ->assertStatus(422);

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs?purpose=nonsense')
        ->assertStatus(422);
});

it('searches inside request and response bodies', function () {
    [$adminToken] = logFixture();

    $get = fn (string $q) => test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs?search=' . urlencode($q))->assertOk()->json('meta.total');

    expect($get('иду в банк'))->toBe(1)
        ->and($get('completion_tokens'))->toBe(1)
        ->and($get('nothing-matches-this'))->toBe(0);
});

it('filters by a date window', function () {
    [$adminToken] = logFixture();

    $get = fn (string $q) => test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs?direction=outbound&' . $q)->assertOk()->json('meta.total');

    $day = fn (int $offset): string => urlencode(now()->addDays($offset)->toIso8601String());

    expect($get('from=' . $day(-1)))->toBe(1)
        ->and($get('from=' . $day(1)))->toBe(0)
        ->and($get('to=' . $day(-1)))->toBe(0)
        ->and($get('to=' . $day(1)))->toBe(1);
});

it('returns full request and response bodies for one row', function () {
    [$adminToken, $outboundId] = logFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/logs/{$outboundId}")
        ->assertOk()
        ->assertJsonPath('id', $outboundId)
        ->assertJsonPath('request_body.model', 'gpt-4o')
        ->assertJsonPath('response_body.usage.prompt_tokens', 1000)
        // Bodies are stored already redacted — the endpoint hands back exactly what is on disk.
        ->assertJsonPath('request_headers.Authorization', '[REDACTED]');

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs/' . Ulid::generate())
        ->assertNotFound();
});

it('keeps the original /request-logs path answering', function () {
    [$adminToken] = logFixture();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/request-logs?direction=inbound')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});
