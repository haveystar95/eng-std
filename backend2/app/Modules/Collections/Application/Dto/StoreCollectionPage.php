<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/** A cursor-paginated page of store collections, ordered by topic for client sectioning. */
final readonly class StoreCollectionPage
{
    /** @param list<StoreCollectionView> $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public bool $hasMore,
    ) {}
}
