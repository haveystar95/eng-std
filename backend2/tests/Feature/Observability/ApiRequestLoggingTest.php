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

it('records a BINARY response as a description, never as its bytes', function () {
    // ЖИВОЙ ДЕФЕКТ TTS-1. `POST /v1/audio/speech` отвечает mp3; байты уезжали в `raw`, и Postgres
    // отбивал вставку («SQLSTATE[22P05] Untranslatable character»). Отбивал ПОСЛЕ успешного
    // вызова, то есть лог ронял работу, за которую уже заплатили: озвучка каждой реплики
    // покупалась на один раз больше, чем нужно, и спасали только ретрай и идемпотентность кэша.
    $mp3 = "\xFF\xFB\x90\x00" . random_bytes(2048);
    Http::fake(['api.openai.com/*' => Http::response($mp3, 200, ['Content-Type' => 'audio/mpeg'])]);

    Http::post('https://api.openai.com/v1/audio/speech', ['model' => 'gpt-4o-mini-tts']);

    $log = ApiRequestLogModel::query()
        ->where('direction', 'outbound')->where('path', '/v1/audio/speech')->first();

    expect($log)->not->toBeNull()
        // jsonb порядок ключей не хранит, поэтому сравниваем по значениям.
        ->and($log->response_body['binary'] ?? null)->toBeTrue()
        ->and($log->response_body['bytes'] ?? null)->toBe(strlen($mp3))
        ->and($log->response_body)->not->toHaveKey('raw')
        // Размер и статус остаются — «во что обошёлся этот вызов» отвечается ими, а не байтами.
        ->and($log->response_bytes)->toBe(strlen($mp3))
        ->and($log->status)->toBe(200);
});

// TTS-2: the voice vendor's calls carry their own tag and their key in a header of its own name.
it('tags the voice vendor’s calls and hides its key', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response("ID3\x04\x00".random_bytes(512), 200, ['Content-Type' => 'audio/mpeg'])]);

    Http::withHeaders(['xi-api-key' => 'sk_voice_secret'])
        ->post('https://api.elevenlabs.io/v1/text-to-speech/voice?output_format=mp3_44100_128', ['text' => 'Hi.']);

    $log = ApiRequestLogModel::query()->where('host', 'api.elevenlabs.io')->first();

    expect($log?->service)->toBe('elevenlabs')
        ->and($log?->request_headers['xi-api-key'] ?? null)->toBe('[REDACTED]')
        ->and($log?->response_body['binary'] ?? null)->toBeTrue();
});

it('still keeps a plain-text body that is not JSON', function () {
    Http::fake(['api.pexels.com/*' => Http::response('rate limit exceeded', 429)]);

    Http::get('https://api.pexels.com/v1/search');

    $log = ApiRequestLogModel::query()->where('host', 'api.pexels.com')->first();

    expect($log?->response_body)->toBe(['raw' => 'rate limit exceeded']);
});
