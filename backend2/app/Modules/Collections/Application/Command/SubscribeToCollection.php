<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Add a store (public/system) collection to the user's library. Idempotent; premium is gated. */
final readonly class SubscribeToCollection
{
    public function __construct(
        public UserId $userId,
        public CollectionId $collectionId,
    ) {}
}
