<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermEnrichmentExportRow;

/** Reads stored enrichment content back out for the proofreading export. Read-only, batched. */
interface TermEnrichmentExportReader
{
    /**
     * @param  list<TermId>  $termIds
     * @return array<string, TermEnrichmentExportRow>  keyed by term id
     */
    public function byIds(array $termIds): array;
}
