<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Port\EnrichmentJournal;

/**
 * Resolves collections to the term ids that still need enriching, deduped across collections (the
 * dictionary is global, so the same term genuinely appears in several collections and must be paid
 * for once) and filtered by the version mark.
 *
 * Lives in Generation's Application because the queue job that needs it may not reach Collections
 * directly — Infrastructure talks to its own Application, which is the only layer allowed the
 * cross-module read.
 */
final readonly class ListPendingEnrichmentTargetsHandler
{
    public function __construct(
        private GetCollectionTermSetHandler $termSet,
        private EnrichmentJournal $journal,
    ) {}

    /** @return list<string> */
    public function __invoke(ListPendingEnrichmentTargets $query): array
    {
        $seen = [];
        foreach ($query->collectionIds as $collectionId) {
            $set = ($this->termSet)(new GetCollectionTermSet($collectionId));
            if ($set === null) {
                continue;
            }
            foreach ($set->termIds as $termId) {
                $seen[$termId] = true;
            }
        }

        return $this->journal->pending(array_keys($seen), $query->generatorVersion);
    }
}
