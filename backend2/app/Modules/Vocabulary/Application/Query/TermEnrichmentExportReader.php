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
     * @param  string  $lang  the COLLECTION's language — the export must show the same translation
     *                        the learner is asked, or the proofreader corrects a row nobody sees.
     * @return array<string, TermEnrichmentExportRow>  keyed by term id
     */
    public function byIds(array $termIds, string $lang): array;
}
