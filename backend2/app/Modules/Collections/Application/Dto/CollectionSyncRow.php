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
    ) {}
}
