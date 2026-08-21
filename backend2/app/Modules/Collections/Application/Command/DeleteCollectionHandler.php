<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;

final readonly class DeleteCollectionHandler
{
    public function __construct(private CollectionRepository $collections) {}

    public function __invoke(DeleteCollection $command): void
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        // Owner-only AND not «Сохранённые». Deleting a folder is deliberately shallow: the pool and
        // the append-only review log are untouched, and every word in it stays enrolled — the only
        // way out of the pool remains «убрать из тренировки».
        $collection->assertDeletableBy($command->actorId);

        $this->collections->delete($command->collectionId);
    }
}
