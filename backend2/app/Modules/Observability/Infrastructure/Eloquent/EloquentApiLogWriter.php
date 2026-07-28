<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use App\Modules\Observability\Application\Dto\ApiLogEntry;
use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EloquentApiLogWriter implements ApiLogWriter
{
    /** Bodies larger than this (JSON-encoded) are dropped for a marker — e.g. 500 stack traces. */
    private const MAX_BODY_BYTES = 16384;

    public function write(ApiLogEntry $entry): void
    {
        try {
            ApiRequestLogModel::query()->create([
                'id' => Ulid::generate(),
                'direction' => $entry->direction,
                'method' => $entry->method,
                'host' => $entry->host,
                'path' => mb_substr($entry->path, 0, 1000),
                'service' => $entry->service,
                'status' => $entry->status,
                'duration_ms' => $entry->durationMs,
                'user_id' => $entry->userId,
                'request_bytes' => $entry->requestBytes,
                'response_bytes' => $entry->responseBytes,
                'request_headers' => $entry->requestHeaders,
                'request_body' => $this->prepareBody($entry->requestBody),
                'response_body' => $this->prepareBody($entry->responseBody),
                'error' => $entry->error,
                'occurred_at' => $entry->occurredAt,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Observability must never break the request it is observing.
            Log::warning('api log write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Drop framework debug stack traces (noise, and never a real API field), then cap the
     * remaining payload so a pathological body can't bloat the table.
     *
     * @param  array<mixed>|null  $body
     * @return array<mixed>|null
     */
    private function prepareBody(?array $body): ?array
    {
        if ($body === null) {
            return null;
        }
        unset($body['trace']); // Laravel's APP_DEBUG=true error shape; keeps message/exception

        $encoded = json_encode($body);
        if (is_string($encoded) && strlen($encoded) > self::MAX_BODY_BYTES) {
            return ['_truncated' => true, 'bytes' => strlen($encoded)];
        }

        return $body;
    }
}
