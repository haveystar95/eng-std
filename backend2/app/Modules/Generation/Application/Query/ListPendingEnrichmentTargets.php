<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Which terms of these collections still need the enrichment станок at this version. */
final readonly class ListPendingEnrichmentTargets
{
    /** @param  list<CollectionId>  $collectionIds */
    public function __construct(
        public array $collectionIds,
        public string $generatorVersion,
    ) {}
}
