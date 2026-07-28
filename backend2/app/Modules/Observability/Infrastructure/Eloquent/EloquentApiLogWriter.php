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
                'request_body' => $entry->requestBody,
                'response_body' => $entry->responseBody,
                'error' => $entry->error,
                'occurred_at' => $entry->occurredAt,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Observability must never break the request it is observing.
            Log::warning('api log write failed', ['error' => $e->getMessage()]);
        }
    }
}
