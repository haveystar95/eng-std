<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;

final readonly class PublishCollectionToStoreHandler
{
    public function __construct(private CollectionRepository $collections) {}

    public function __invoke(PublishCollectionToStore $command): void
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        $collection->publishToStore($command->isPremium);

        $this->collections->save($collection);
    }
}
