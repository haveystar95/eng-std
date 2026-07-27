<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;

final readonly class AddTermToCollectionHandler
{
    public function __construct(private CollectionRepository $collections) {}

    public function __invoke(AddTermToCollection $command): void
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        $collection->assertEditableBy($command->actorId);
        $collection->addTerm($command->termId, $command->note);

        $this->collections->save($collection);
    }
}
