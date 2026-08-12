<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\EnrichmentTargetView;

/**
 * Batch-reads term content for the enrichment станок. Deliberately knows nothing about runs or
 * versions: "which of these have I already done" is the caller's bookkeeping (Generation owns that
 * table), so this reader stays a plain content read and Vocabulary never learns about generators.
 */
interface EnrichmentTargetReader
{
    /**
     * @param  list<TermId>  $termIds
     * @return array<string, EnrichmentTargetView>  keyed by term id; absent = no such term
     */
    public function byIds(array $termIds): array;

    /**
     * Of these terms, which ones' PINNED example carries fewer than $minDistractors — i.e. cannot host
     * a mode that needs that many wrong options.
     *
     * This is the top-up selector, and it deliberately asks about CONTENT rather than about runs: a
     * proofreader deleting bad distractors leaves a term that is "already processed" and nonetheless
     * short, so the version mark is exactly the wrong question here. Terms without an example are not
     * returned — they are not short of distractors, they have nothing to build them against.
     *
     * @param  list<TermId>  $termIds
     * @return list<string>  term ids, order preserved
     */
    public function underCovered(array $termIds, int $minDistractors): array;
}
