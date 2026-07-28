<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

final readonly class CreateGeneratedCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private Clock $clock,
    ) {}

    public function __invoke(CreateGeneratedCollection $command): CollectionId
    {
        $collection = Collection::createGenerated(
            id: CollectionId::generate(),
            ownerId: $command->ownerId,
            title: $command->title,
            sourceLang: $command->sourceLang,
            targetLang: $command->targetLang,
            createdAt: $this->clock->now(),
            description: $command->description,
            topic: $command->topic,
        );

        $this->collections->save($collection);

        return $collection->id();
    }
}
