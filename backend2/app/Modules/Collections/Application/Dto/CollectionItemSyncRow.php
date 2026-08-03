<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use DateTimeImmutable;

/** One collection-item change for delta sync. `deleted` (from deleted_at) means op=delete. */
final readonly class CollectionItemSyncRow
{
    public function __construct(
        public string $collectionId,
        public string $termId,
        public bool $deleted,
        public DateTimeImmutable $updatedAt,
        public int $position,
        public ?string $note,
    ) {}
}
