<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Which terms of these collections still need the enrichment станок at this version. */
final readonly class ListPendingEnrichmentTargets
{
    /**
     * @param  list<CollectionId>  $collectionIds
     * @param  int|null  $topUpBelow  when set, select by COVERAGE instead of by the version mark:
     *        terms whose pinned example carries fewer than this many distractors. That is the top-up
     *        question — a proofreader's deletions leave terms both "already processed" and short, and
     *        the mark would hide exactly those.
     */
    public function __construct(
        public array $collectionIds,
        public string $generatorVersion,
        public ?int $topUpBelow = null,
    ) {}
}
