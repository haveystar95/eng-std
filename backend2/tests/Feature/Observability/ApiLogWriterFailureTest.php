<?php

declare(strict_types=1);

use App\Modules\Observability\Application\Dto\ApiLogEntry;
use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Observability\Infrastructure\Eloquent\ApiRequestLogModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function logEntry(string $purpose): ApiLogEntry
{
    return new ApiLogEntry(
        direction: 'outbound',
        method: 'POST',
        path: '/v1/chat/completions',
        host: 'api.openai.com',
        service: 'openai',
        purpose: $purpose,
        collectionId: null,
        status: 200,
        durationMs: 12,
        userId: null,
        requestBytes: 10,
        responseBytes: 20,
        requestHeaders: ['Authorization' => '[REDACTED]'],
        requestBody: ['model' => 'gpt-4o-mini'],
        responseBody: ['ok' => true],
        error: null,
        occurredAt: new DateTimeImmutable('2026-08-13T12:00:00+00:00'),
    );
}

// D-1, half one: the purpose the language barrier's repairer emits was outside the column's CHECK,
// so every repair call ever made lost its log row. The constraint has to admit it.
it('accepts translation_repair as an outbound purpose', function () {
    $id = app(ApiLogWriter::class)->write(logEntry('translation_repair'));

    expect($id)->not->toBeNull();
    expect(ApiRequestLogModel::query()->find($id)?->purpose)->toBe('translation_repair');
});

// D-1, half two: a write that still fails must not take the observed call down with it — and must
// not disappear either. The previous version logged the message alone, which is why a whole day of
// dropped rows read as noise.
it('logs a failed write with identifying context instead of swallowing it quietly', function () {
    Log::spy();

    $id = app(ApiLogWriter::class)->write(logEntry('not_a_real_purpose'));

    expect($id)->toBeNull();                                     // the caller is told nothing landed
    expect(ApiRequestLogModel::query()->count())->toBe(0);       // and nothing did

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'NOT recorded')
                && $context['purpose'] === 'not_a_real_purpose'
                && $context['host'] === 'api.openai.com'
                && $context['direction'] === 'outbound'
                && $context['method'] === 'POST'
                && $context['status'] === 200
                && $context['exception'] instanceof Throwable
                && is_string($context['error']) && $context['error'] !== '';
        })
        ->once();
});

it('does not throw out of a failed write', function () {
    Log::spy();

    expect(fn (): ?string => app(ApiLogWriter::class)->write(logEntry('not_a_real_purpose')))
        ->not->toThrow(Throwable::class);
});

// "Never break the request it is observing" was only true outside a transaction: Postgres aborts the
// whole transaction on any failed statement, so a swallowed CHECK violation used to leave the
// CALLER's next query dead with "current transaction is aborted". The savepoint is what makes the
// promise hold — and a good write landing right after a failed one is the proof.
it('leaves the surrounding transaction usable after a failed write', function () {
    Log::spy();

    DB::transaction(function (): void {
        app(ApiLogWriter::class)->write(logEntry('not_a_real_purpose'));

        // Same transaction, after the failure: both of these died before the savepoint existed.
        expect(ApiRequestLogModel::query()->count())->toBe(0);
        expect(app(ApiLogWriter::class)->write(logEntry('translation_repair')))->not->toBeNull();
    });

    expect(ApiRequestLogModel::query()->count())->toBe(1);
});

// The bodies are the large, secret-bearing half and identify nothing — they stay out of the line.
it('keeps request and response bodies out of the failure log', function () {
    Log::spy();

    app(ApiLogWriter::class)->write(logEntry('not_a_real_purpose'));

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => ! isset($context['request_body'], $context['response_body'])
            && ! isset($context['request_headers']))
        ->once();
});
