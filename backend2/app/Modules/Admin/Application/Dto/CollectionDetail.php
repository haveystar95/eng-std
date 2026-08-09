<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A collection with its ordered terms. */
final readonly class CollectionDetail
{
    /** @param list<CollectionTermRow> $terms */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $description,
        public ?string $topic,
        public ?string $ownerId,
        public string $source,
        public string $sourceLang,
        public string $targetLang,
        public int $itemsCount,
        public ?string $createdAt,
        public array $terms,
    ) {}
}
