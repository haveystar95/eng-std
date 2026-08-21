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

it('does not log the back-office reading the log', function () {
    // The panel is the instrument, not the traffic. Reading a log used to write a row whose
    // response body WAS the row just read — an empty request body beside a response body carrying
    // somebody else's request body, which reads as two mixed-up fields and is not.
    [, $adminToken] = adminActor();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/logs')
        ->assertOk();

    expect(ApiRequestLogModel::query()->where('path', 'like', 'admin/api%')->count())->toBe(0);
});

it('still logs the product API while the back-office is skipped', function () {
    [, $token] = logUser();
    [, $adminToken] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/stats')->assertOk();
    test()->withHeader('Authorization', "Bearer {$adminToken}")->getJson('/admin/api/logs')->assertOk();

    expect(ApiRequestLogModel::query()->where('direction', 'inbound')->pluck('path')->all())
        ->toBe(['api/v1/stats']);
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
