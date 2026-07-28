<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Soft-delete a collection (owner only) so offline clients receive a tombstone. */
final readonly class DeleteCollection
{
    public function __construct(
        public CollectionId $collectionId,
        public UserId $actorId,
    ) {}
}
