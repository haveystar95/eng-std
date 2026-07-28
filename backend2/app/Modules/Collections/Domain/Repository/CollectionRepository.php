<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Repository;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

interface CollectionRepository
{
    public function findById(CollectionId $id): ?Collection;

    public function save(Collection $collection): void;

    /** Soft-delete so offline clients receive a tombstone via delta sync. */
    public function delete(CollectionId $id): void;
}
