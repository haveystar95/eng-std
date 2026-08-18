<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

/**
 * Read access to a logged API call, for the rare case something needs to look back at what a
 * vendor actually returned (e.g. diagnosing or recovering from a past generation). Separate from
 * {@see ApiLogWriter} because nothing in the normal request path reads its own log back.
 */
interface ApiRequestLogReader
{
    /**
     * The decoded `response_body` of one log row, or null if the row doesn't exist or the body
     * was dropped at log time — a body over the size cap is replaced with a
     * `{"_truncated":true,"bytes":N}` placeholder, see
     * {@see \App\Modules\Observability\Infrastructure\Eloquent\EloquentApiLogWriter::prepareBody()}.
     *
     * @return array<string, mixed>|null
     */
    public function findResponseBody(string $logId): ?array;
}
