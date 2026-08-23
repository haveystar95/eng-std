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
     * The decoded `response_body` of one log row, or null if the row doesn't exist. A body over
     * the size cap is stored as `{"_truncated":true,"bytes":N,"preview":"<head slice of raw
     * JSON>"}` instead of its decoded self — so a caller that wants the real structure must
     * check for `_truncated` rather than assume the vendor's shape, see
     * {@see \App\Modules\Observability\Infrastructure\Eloquent\EloquentApiLogWriter::prepareBody()}.
     *
     * @return array<string, mixed>|null
     */
    public function findResponseBody(string $logId): ?array;

    /**
     * Per-model prompt-token totals for OUTBOUND calls in a window, including the share the vendor
     * served from its own prompt cache (`usage.prompt_tokens_details.cached_tokens`).
     *
     * Lives here because the table does. A caller that wanted to know what a run really cost would
     * otherwise join `api_request_logs` from outside the module that owns it — and the reason the
     * question comes here at all is that a cost estimate built from stored totals prices every
     * input token at full rate, which overstates any run that re-sends one system prompt many times.
     *
     * @return array<string, array{calls: int, prompt_tokens: int, cached_tokens: int}>  keyed by model
     */
    public function promptCacheByModel(string $purpose, \DateTimeInterface $from, \DateTimeInterface $to): array;
}
