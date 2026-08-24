<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Application\Service\DefaultCollectionPair;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

final readonly class CreateCustomCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private Clock $clock,
        private DefaultCollectionPair $defaultPair,
    ) {}

    public function __invoke(CreateCustomCollection $command): CollectionId
    {
        // Idempotent on a client-supplied id: re-sending returns the existing one.
        if ($command->id !== null && $this->collections->findById($command->id) !== null) {
            return $command->id;
        }

        // A pair the caller did not name is the OWNER's default, not `ru→en` (DECISIONS п. 142).
        // Read once, so a half-named pair takes the other half from the same place.
        $default = $this->defaultPair->forOwner($command->ownerId);

        $collection = Collection::createCustom(
            id: $command->id ?? CollectionId::generate(),
            ownerId: $command->ownerId,
            title: $command->title,
            sourceLang: $command->sourceLang ?? $default->sourceLang,
            targetLang: $command->targetLang ?? $default->targetLang,
            createdAt: $this->clock->now(),
            description: $command->description,
            topic: $command->topic,
        );

        $this->collections->save($collection);

        return $collection->id();
    }
}
