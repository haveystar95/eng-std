<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryCollectionRepository implements CollectionRepository
{
    /** @var array<string, Collection> */
    private array $byId = [];

    public function findById(CollectionId $id): ?Collection
    {
        return $this->byId[$id->value] ?? null;
    }

    public function findDefaultFor(UserId $ownerId): ?Collection
    {
        foreach ($this->byId as $collection) {
            if ($collection->isDefault() && $collection->ownerId()?->equals($ownerId) === true) {
                return $collection;
            }
        }

        return null;
    }

    public function save(Collection $collection): void
    {
        $this->byId[$collection->id()->value] = $collection;
    }

    public function delete(CollectionId $id): void
    {
        unset($this->byId[$id->value]);
    }

    public function count(): int
    {
        return count($this->byId);
    }
}
