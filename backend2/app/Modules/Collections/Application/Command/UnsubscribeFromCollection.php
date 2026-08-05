<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Remove a collection from the user's library ("Убрать из моих"). Idempotent. */
final readonly class UnsubscribeFromCollection
{
    public function __construct(
        public UserId $userId,
        public CollectionId $collectionId,
    ) {}
}
