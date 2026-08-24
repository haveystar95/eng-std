<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Application\Service\DefaultCollectionPair;
use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

final readonly class EnsureDefaultCollectionHandler
{
    /**
     * The folder's title at birth. The owner may rename it the second after — the flag is what the
     * app addresses, not this string ({@see Collection::createDefault()}).
     */
    public const TITLE = 'Сохранённые';

    public function __construct(
        private CollectionRepository $collections,
        private Clock $clock,
        private DefaultCollectionPair $defaultPair,
    ) {}

    public function __invoke(EnsureDefaultCollection $command): CollectionId
    {
        $existing = $this->collections->findDefaultFor($command->ownerId);
        if ($existing !== null) {
            return $existing->id();
        }

        $default = $this->defaultPair->forOwner($command->ownerId);

        $collection = Collection::createDefault(
            id: CollectionId::generate(),
            ownerId: $command->ownerId,
            title: self::TITLE,
            sourceLang: $command->sourceLang ?? $default->sourceLang,
            targetLang: $command->targetLang ?? $default->targetLang,
            createdAt: $this->clock->now(),
        );

        $this->collections->save($collection);

        return $collection->id();
    }
}
