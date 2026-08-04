<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Repository\CollectionRepository;

final readonly class AttachCollectionImageHandler
{
    public function __construct(private CollectionRepository $collections) {}

    public function __invoke(AttachCollectionImage $command): void
    {
        $collection = $this->collections->findById($command->collectionId);
        if ($collection === null) {
            return; // vanished collection → no-op, not an error
        }

        $collection->attachImage($command->imageUrl, $command->imageAuthor, $command->imageAuthorUrl);
        $this->collections->save($collection);
    }
}
