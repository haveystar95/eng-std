<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** The export is grouped by collection, because that is the unit a person proofreads in one sitting. */
final readonly class EnrichmentExportGroup
{
    /** @param  list<EnrichmentExportItem>  $items */
    public function __construct(
        public string $collectionId,
        public string $title,
        public array $items,
    ) {}
}
