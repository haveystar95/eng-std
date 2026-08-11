<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Dto\EnrichmentExportGroup;
use App\Modules\Generation\Application\Dto\EnrichmentExportItem;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermEnrichmentExportReader;

/**
 * Assembles the proofreading export: content from Vocabulary, flags from Generation's own journal,
 * grouped by collection and kept in collection order (`position`), so the file reads in the same
 * order as the collection itself.
 *
 * A term with nothing written and nothing flagged is skipped — the export is a worklist, and padding
 * it with clean terms is how a worklist stops being read.
 */
final readonly class ExportEnrichmentHandler
{
    public function __construct(
        private GetCollectionTermSetHandler $termSet,
        private TermEnrichmentExportReader $content,
        private EnrichmentJournal $journal,
    ) {}

    /** @return list<EnrichmentExportGroup> */
    public function __invoke(ExportEnrichment $query): array
    {
        $groups = [];
        foreach ($query->collectionIds as $collectionId) {
            $set = ($this->termSet)(new GetCollectionTermSet($collectionId));
            if ($set === null) {
                continue;
            }

            $rows = $this->content->byIds(array_map(
                static fn (string $id): TermId => TermId::fromString($id),
                $set->termIds,
            ));
            $findings = $this->byTerm($this->journal->findingsFor($set->termIds, $query->generatorVersion));

            $items = [];
            foreach ($set->termIds as $termId) {
                $row = $rows[$termId] ?? null;
                if ($row === null) {
                    continue;
                }
                $termFindings = $findings[$termId] ?? [];
                if ($row->variants === [] && $row->distractors === [] && $termFindings === []) {
                    continue;
                }
                $items[] = new EnrichmentExportItem($row, $termFindings);
            }

            $groups[] = new EnrichmentExportGroup($collectionId->value, $set->title, $items);
        }

        return $groups;
    }

    /**
     * @param  list<EnrichmentFinding>  $findings
     * @return array<string, list<EnrichmentFinding>>
     */
    private function byTerm(array $findings): array
    {
        $out = [];
        foreach ($findings as $finding) {
            $out[$finding->termId][] = $finding;
        }

        return $out;
    }
}
