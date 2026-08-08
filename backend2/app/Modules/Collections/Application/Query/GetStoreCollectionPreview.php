<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

final readonly class GetStoreCollectionPreview
{
    public function __construct(
        public CollectionId $collectionId,
        public int $limit = 5,
    ) {}
}
