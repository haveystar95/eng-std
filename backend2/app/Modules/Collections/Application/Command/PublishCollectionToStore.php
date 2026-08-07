<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Promote an existing collection to curated store content (ownerless, system, public). */
final readonly class PublishCollectionToStore
{
    public function __construct(
        public CollectionId $collectionId,
        public bool $isPremium = false,
    ) {}
}
