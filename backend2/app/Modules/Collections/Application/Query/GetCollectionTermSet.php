<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Read a collection's title/description + ordered term ids, for cloning its term set. */
final readonly class GetCollectionTermSet
{
    public function __construct(
        public CollectionId $collectionId,
    ) {}
}
