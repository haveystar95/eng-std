<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Collections\Application\Dto\CollectionTermSetView;
use App\Modules\Collections\Domain\Repository\CollectionRepository;

final readonly class GetCollectionTermSetHandler
{
    public function __construct(
        private CollectionRepository $collections,
    ) {}

    public function __invoke(GetCollectionTermSet $query): ?CollectionTermSetView
    {
        $collection = $this->collections->findById($query->collectionId);
        if ($collection === null) {
            return null;
        }

        return new CollectionTermSetView(
            title: $collection->title(),
            description: $collection->description(),
            termIds: array_map(static fn ($item): string => $item->termId->value, $collection->items()),
        );
    }
}
