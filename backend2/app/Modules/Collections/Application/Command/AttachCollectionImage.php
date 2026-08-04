<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Attach a found cover photo to a collection. Cross-module entry (Generation's attach job).
 * Idempotent and never-overwrite — the aggregate ignores it once a cover is set.
 */
final readonly class AttachCollectionImage
{
    public function __construct(
        public CollectionId $collectionId,
        public ?string $imageUrl,
        public ?string $imageAuthor,
        public ?string $imageAuthorUrl,
    ) {}
}
