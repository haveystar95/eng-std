<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\ContentLabelCount;
use App\Modules\Admin\Application\Dto\ContentTermFacts;

/**
 * The content tables read as a REPORTING PROJECTION — terms, their pinned examples, the distractors
 * and variants the станок wrote, the suppressions that removed some of them, and the rejections the
 * language barrier logged.
 *
 * Read-only by contract: nothing behind this port writes, and the screens built on it deliberately
 * offer no button that would. The догон is a command an operator runs in a terminal, on purpose —
 * spending money on the model is not a thing an admin panel should be one misclick away from.
 */
interface AdminContentHealthReader
{
    /**
     * Every live term's raw content, optionally narrowed to one collection.
     *
     * The whole dictionary is a few hundred rows, so this is read in full and judged in PHP by
     * Learning's own gate rather than approximated in SQL — the alternative is a second, subtly
     * different definition of «годный дистрактор» living in a query.
     *
     * @return list<ContentTermFacts>
     */
    public function termFacts(?string $collectionId = null): array;

    public function termFactsById(string $termId): ?ContentTermFacts;

    /** @return list<CollectionRefRow> live collections, newest last */
    public function collections(): array;

    public function collection(string $collectionId): ?CollectionRefRow;

    /** @return list<ContentLabelCount> `review` / `audit` */
    public function suppressionsBySource(): array;

    /**
     * The sentences removed for one term, newest first. These rows OUTLIVE the distractors they were
     * about — that is the point of the table — so they are reported as their own list, never merged
     * with the live ones.
     *
     * @return list<array{sentence: string, source: string, created_at: string|null}>
     */
    public function suppressionsForTerm(string $termId): array;

    /** @return list<ContentLabelCount> which field of a refused item was in the wrong language */
    public function rejectionsByField(): array;

    /** @return list<array{version: string, created_at: string|null}> every enrichment mark on a term */
    public function enrichmentVersionsForTerm(string $termId): array;
}
