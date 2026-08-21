<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Repository;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface CollectionRepository
{
    public function findById(CollectionId $id): ?Collection;

    /**
     * This owner's «Сохранённые» folder, or null if they have never saved a word.
     *
     * By the FLAG, never by the title — the owner may rename the folder, and a lookup by name would
     * quietly start creating a second one the moment they did.
     */
    public function findDefaultFor(UserId $ownerId): ?Collection;

    public function save(Collection $collection): void;

    /** Soft-delete so offline clients receive a tombstone via delta sync. */
    public function delete(CollectionId $id): void;
}
