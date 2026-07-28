<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use DateTimeImmutable;

/** Detail view of a collection with its ordered items. */
final readonly class CollectionView
{
    /** @param list<CollectionItemView> $items */
    public function __construct(
        public string $id,
        public string $type,
        public string $source,
        public string $title,
        public ?string $description,
        public string $sourceLang,
        public string $targetLang,
        public string $visibility,
        public int $itemsCount,
        public DateTimeImmutable $createdAt,
        public array $items,
    ) {}
}
