<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/** A cursor-paginated page of collection summaries. */
final readonly class CollectionPage
{
    /** @param list<CollectionSummaryView> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public bool $hasMore,
    ) {}
}
