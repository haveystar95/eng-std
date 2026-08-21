<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use DateTimeImmutable;

/** One collection change for delta sync. `deleted` (from deleted_at) means op=delete. */
final readonly class CollectionSyncRow
{
    public function __construct(
        public string $id,
        public bool $deleted,
        public DateTimeImmutable $updatedAt,
        public ?string $title,
        public ?string $description,
        public ?string $topic,
        public ?string $sourceLang,
        public ?string $targetLang,
        public int $itemsCount,
        public ?string $source, // curated | ai | user — drives the client's badge/origin
        public ?string $type,   // system | shared | custom — my / store / generated distinction
        public ?string $imageUrl = null,        // Pexels cover (null = none/placeholder)
        public ?string $imageAuthor = null,     // photographer credit
        public ?string $imageAuthorUrl = null,  // link to the photographer
        // «Сохранённые»: the folder a one-tap save lands in. The client greys out its delete action
        // and names it in the save confirmation, so it has to ride the delta like any other fact.
        public bool $isDefault = false,
    ) {}
}
