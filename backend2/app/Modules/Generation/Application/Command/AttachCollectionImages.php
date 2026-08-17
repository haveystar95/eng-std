<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Attach stock photos to a generated collection's terms and its cover, out of band. */
final readonly class AttachCollectionImages
{
    public function __construct(
        public CollectionId $collectionId,
    ) {}
}
