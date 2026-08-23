<?php

declare(strict_types=1);

use App\Modules\Observability\Application\Dto\ApiLogEntry;
use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Observability\Infrastructure\Eloquent\ApiRequestLogModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<mixed>  $requestBody
 */
function bodyCapEntry(array $requestBody): ApiLogEntry
{
    return new ApiLogEntry(
        direction: 'outbound',
        method: 'POST',
        path: '/v1/chat/completions',
        host: 'api.openai.com',
        service: 'openai',
        purpose: 'generation',
        collectionId: null,
        status: 200,
        durationMs: 12,
        userId: null,
        requestBytes: 10,
        responseBytes: 20,
        requestHeaders: ['Authorization' => '[REDACTED]'],
        requestBody: $requestBody,
        responseBody: ['ok' => true],
        error: null,
        occurredAt: new DateTimeImmutable('2026-08-21T12:00:00+00:00'),
    );
}

// The cap used to be 16 KB, which is INSIDE the normal size range of a generation prompt (it
// carries the collection's existing terms for dedup): 84% of generation prompts and 43% of
// enrichment prompts were dropped — the exact rows the log gets opened for.
it('stores a real-size generation prompt whole', function () {
    $prompt = str_repeat('дедуп-термин, ', 1000);  // ~24 KB of UTF-8 — a real prompt's size
    $body = ['model' => 'gpt-4o', 'messages' => [['role' => 'system', 'content' => $prompt]]];

    expect(strlen((string) json_encode($body, JSON_UNESCAPED_UNICODE)))->toBeGreaterThan(16384);

    $id = app(ApiLogWriter::class)->write(bodyCapEntry($body));

    $stored = ApiRequestLogModel::query()->find($id)?->request_body;
    expect($stored)->toBe($body)
        ->and($stored['_truncated'] ?? null)->toBeNull();
});

// Past the cap the row keeps a head slice, not just a byte count: `_truncated` used to mean
// "dropped", leaving a reader with nothing to read and nothing to salvage.
it('keeps a readable head slice of an over-cap body instead of dropping it', function () {
    // Cyrillic filler on purpose: the slice has to stop at a character boundary. A byte-exact
    // substr would cut 'я' in half, and Postgres refuses invalid UTF-8 — the INSERT would fail
    // and the row would be lost, which is the very outcome this branch exists to avoid.
    $body = ['model' => 'gpt-4o', 'filler' => str_repeat('я', 40000)];
    $encoded = (string) json_encode($body, JSON_UNESCAPED_UNICODE);

    $id = app(ApiLogWriter::class)->write(bodyCapEntry($body));

    $stored = ApiRequestLogModel::query()->find($id)?->request_body;
    expect($stored['_truncated'] ?? null)->toBeTrue()
        ->and($stored['bytes'] ?? null)->toBe(strlen($encoded))
        ->and($stored['preview'] ?? null)->toBe(mb_strcut($encoded, 0, 8192))
        ->and(mb_check_encoding($stored['preview'], 'UTF-8'))->toBeTrue()
        // The head slice reaches the fields that identify the call.
        ->and($stored['preview'])->toContain('gpt-4o');
});
