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

    /**
     * Un-mark a term, so the next run at this version sees it as pending again.
     *
     * The one thing that legitimately invalidates a finished term is its CONTENT changing underneath
     * the machinery: a replaced example orphans the distractors built against the old sentence, and a
     * term left marked done would keep its hole forever (audit A2, on the four terms repaired after
     * they had already been marked). Idempotent — un-marking an unmarked term is a no-op.
     */
    public function clearMark(string $termId, string $generatorVersion): void;

    /** @param  list<EnrichmentFinding>  $findings */
    public function recordFindings(array $findings, string $generatorVersion): void;

    /**
     * UNACKNOWLEDGED findings for these terms at this version — the proofreading worklist. A finding a
     * human has already ruled on stays in the log but leaves the list.
     *
     * @param  list<string>  $termIds
     * @return list<EnrichmentFinding>
     */
    public function findingsFor(array $termIds, string $generatorVersion): array;

    /**
     * Record that a human has ruled on every still-open finding of this version, and return how many.
     * Idempotent: acknowledging twice acknowledges nothing new.
     */
    public function acknowledgeOpenFindings(string $generatorVersion, ?string $note = null): int;
}
