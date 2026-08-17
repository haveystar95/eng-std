<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Read back what a run wrote, grouped by collection, for a human to proofread. */
final readonly class ExportEnrichment
{
    /** @param  list<CollectionId>  $collectionIds */
    public function __construct(
        public array $collectionIds,
        public string $generatorVersion,
    ) {}
}
