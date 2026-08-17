<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;

final readonly class RemoveTermFromCollectionHandler
{
    public function __construct(private CollectionRepository $collections) {}

    public function __invoke(RemoveTermFromCollection $command): void
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        $collection->assertEditableBy($command->actorId);
        $collection->removeTerm($command->termId);

        $this->collections->save($collection);
    }
}
