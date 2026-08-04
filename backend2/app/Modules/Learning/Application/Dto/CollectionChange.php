<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/**
 * A collection change for the sync feed, in Learning's own terms so the Presentation layer never
 * reaches into Collections' Application DTOs (deptrac: LearningPresentation → CollectionsApplication
 * is not allowed). Mapped from Collections' CollectionSyncRow in the handler.
 */
final readonly class CollectionChange
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
        public ?string $source,
        public ?string $type,
        public ?string $imageUrl = null,
        public ?string $imageAuthor = null,
        public ?string $imageAuthorUrl = null,
    ) {}
}
