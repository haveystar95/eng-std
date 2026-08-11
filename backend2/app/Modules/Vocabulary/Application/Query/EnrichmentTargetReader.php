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
}
