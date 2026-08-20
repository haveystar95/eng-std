<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * Generation's own seam onto Observability's logged API calls — kept as a Generation-owned port
 * (rather than depending on `Observability\Application\ApiRequestLogReader` directly) because
 * Application layers may only depend on their own module's Domain plus other modules'
 * Application, and this needs an Infrastructure-level bridge either way (see
 * `GenerationInfrastructure`'s allowed edge onto `ObservabilityApplication` in deptrac.yaml).
 */
interface LoggedResponseReader
{
    /** @return array<string, mixed>|null */
    public function findResponseBody(string $logId): ?array;

    /**
     * How much of a window's PROMPT was served from the vendor's cache.
     *
     * The bake-off prices every input token at full rate, which overstates the bill whenever a run
     * re-sends the same system prompt — and a per-term stage does exactly that ten times in a row.
     * The vendor reports the cached share in `usage`, but the sandbox stores only the totals, so
     * this reads it back out of the outbound log the calls already went through.
     *
     * @return array<string, array{calls: int, prompt_tokens: int, cached_tokens: int}>  keyed by model
     */
    public function promptCacheByModel(string $purpose, \DateTimeInterface $from, \DateTimeInterface $to): array;
}
