<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;

/**
 * The run's own bookkeeping: which terms this generator version has already handled, and what a
 * human needs to look at. Generation owns these tables — Vocabulary stores the CONTENT and is
 * deliberately kept ignorant of generators and versions.
 */
interface EnrichmentJournal
{
    /**
     * The subset of $termIds this version has NOT processed yet, order preserved. This is the
     * idempotency filter: it is what makes re-running a collection cost nothing for the terms that
     * are done, and what makes a failed chunk resumable.
     *
     * @param  list<string>  $termIds
     * @return list<string>
     */
    public function pending(array $termIds, string $generatorVersion): array;

    /** Mark a term handled at this version — written even when the model produced nothing usable. */
    public function markDone(string $termId, string $generatorVersion): void;

    /** @param  list<EnrichmentFinding>  $findings */
    public function recordFindings(array $findings, string $generatorVersion): void;

    /**
     * Findings for these terms at this version, for the proofreading export.
     *
     * @param  list<string>  $termIds
     * @return list<EnrichmentFinding>
     */
    public function findingsFor(array $termIds, string $generatorVersion): array;
}
