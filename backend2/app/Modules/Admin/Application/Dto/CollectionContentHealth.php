<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One collection's terms, most under-stocked first — the middle level of «Здоровье контента»,
 * between the summary and a single term's passport.
 */
final readonly class CollectionContentHealth
{
    /** @param  list<ContentHealthTermRow>  $terms */
    public function __construct(
        public string $collectionId,
        public string $title,
        public string $type,
        public array $terms,
        public int $needsEnrichment,
        public int $withoutExample,
        public int $pickCorrectReady,
        public float $estimatedTopUpUsd,
        /** Ready to copy: the догон for THIS collection. */
        public string $topUpCommand,
        public int $minDistractors,
        public float $costPerTermUsd,
    ) {}
}
