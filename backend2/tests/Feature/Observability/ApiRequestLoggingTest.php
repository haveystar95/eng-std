<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Observability\Infrastructure\Eloquent\ApiRequestLogModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: string} */
function logUser(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('test-device')->plainTextToken];
}

it('logs an inbound API request after it completes', function () {
    [$user, $token] = logUser();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk();

    $log = ApiRequestLogModel::query()->where('direction', 'inbound')->where('path', 'api/v1/stats')->first();

    expect($log)->not->toBeNull();
    expect($log->method)->toBe('GET');
    expect($log->status)->toBe(200);
    expect($log->user_id)->toBe($user->id);
    expect($log->duration_ms)->not->toBeNull();
});

it('redacts secrets in a logged inbound request body', function () {
    [, $token] = logUser();

    // Invalid on purpose (reviews required) — we only care that the body is logged redacted.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [], 'id_token' => 'ya29.super-secret']);

    $log = ApiRequestLogModel::query()->where('direction', 'inbound')->where('path', 'api/v1/reviews/batch')->first();

    expect($log)->not->toBeNull();
    expect($log->request_body['id_token'])->toBe('[REDACTED]');
});

it('logs an outbound external call with credentials redacted', function () {
    Http::fake(['api.openai.com/*' => Http::response(['ok' => true], 200)]);

    Http::withToken('sk-live-secret')
        ->post('https://api.openai.com/v1/chat/completions', ['model' => 'gpt-4o', 'api_key' => 'zzz']);

    $log = ApiRequestLogModel::query()->where('direction', 'outbound')->first();

    expect($log)->not->toBeNull();
    expect($log->host)->toBe('api.openai.com');
    expect($log->service)->toBe('openai');
    expect($log->status)->toBe(200);
    expect($log->request_headers['Authorization'])->toBe('[REDACTED]');
    expect($log->request_body['api_key'])->toBe('[REDACTED]');
    expect($log->request_body['model'])->toBe('gpt-4o');
});
