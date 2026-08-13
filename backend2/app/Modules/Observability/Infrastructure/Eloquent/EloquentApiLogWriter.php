<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use App\Modules\Observability\Application\Dto\ApiLogEntry;
use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EloquentApiLogWriter implements ApiLogWriter
{
    /** Bodies larger than this (JSON-encoded) are dropped for a marker — e.g. 500 stack traces. */
    private const MAX_BODY_BYTES = 16384;

    public function write(ApiLogEntry $entry): ?string
    {
        try {
            $id = Ulid::generate();
            // Wrapped so a failed INSERT rolls back to a SAVEPOINT rather than poisoning whatever
            // transaction the observed call happens to be inside. Postgres aborts an entire
            // transaction on any failed statement, so without this the catch below would "not break
            // the request it is observing" only when there was no transaction to break — and the
            // next query of the caller's own work would die with "current transaction is aborted".
            // Laravel turns a nested transaction() into a savepoint, and starts a real one when
            // there is no outer transaction, so this is correct on both paths.
            DB::transaction(fn () => ApiRequestLogModel::query()->create([
                'id' => $id,
                'direction' => $entry->direction,
                'method' => $entry->method,
                'host' => $entry->host,
                'path' => mb_substr($entry->path, 0, 1000),
                'service' => $entry->service,
                'purpose' => $entry->purpose,
                'collection_id' => $entry->collectionId,
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
            ]));

            return $id;
        } catch (Throwable $e) {
            // Observability must never break the request it is observing — so this still swallows.
            // What it must NOT do is swallow QUIETLY: the previous version logged the message alone,
            // and a `purpose` outside the CHECK constraint therefore dropped 256 outbound calls over
            // a day with nothing in the line to say WHICH calls or WHY. The identifying fields are
            // what turns "a write failed" into "translation_repair calls to api.openai.com are not
            // being recorded"; the exception object carries the trace. Bodies and headers stay out —
            // they are the large, secret-bearing half, and they identify nothing.
            $this->report('api log write failed — this outbound/inbound call is NOT recorded', $e, [
                'direction' => $entry->direction,
                'method' => $entry->method,
                'host' => $entry->host,
                'path' => mb_substr($entry->path, 0, 200),
                'service' => $entry->service,
                'purpose' => $entry->purpose,
                'collection_id' => $entry->collectionId,
                'status' => $entry->status,
                'occurred_at' => $entry->occurredAt->format(DATE_ATOM),
            ]);

            return null;
        }
    }

    public function linkCollection(array $logIds, string $collectionId): void
    {
        if ($logIds === []) {
            return;
        }

        try {
            // Savepoint, for the same reason as write() — see the comment there.
            DB::transaction(fn () => ApiRequestLogModel::query()
                ->whereIn('id', $logIds)
                ->update(['collection_id' => $collectionId]));
        } catch (Throwable $e) {
            $this->report('api log collection link failed — these rows keep no collection', $e, [
                'collection_id' => $collectionId,
                'log_ids' => $logIds,
            ]);
        }
    }

    /**
     * One shape for both failures: a named error with the identifying context and the exception
     * itself. `error`, not `warning` — a log row that was supposed to exist and does not is a defect
     * in the ledger the spend reports are read from, and `warning` is the level at which it hid.
     *
     * @param  array<string, mixed>  $context
     */
    private function report(string $message, Throwable $e, array $context): void
    {
        Log::error($message, [
            ...$context,
            'exception_class' => $e::class,
            'error' => $e->getMessage(),
            'exception' => $e,
        ]);
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
