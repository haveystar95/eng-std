<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/** A collection-item change for the sync feed, in Learning's own terms (see CollectionChange). */
final readonly class CollectionItemChange
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
