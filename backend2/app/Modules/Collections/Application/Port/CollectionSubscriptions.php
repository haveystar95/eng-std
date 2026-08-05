<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** The user↔collection library membership (user_collections). Store subscribe/unsubscribe. */
interface CollectionSubscriptions
{
    /** Idempotent: subscribing an already-subscribed collection is a no-op (PK guards duplicates). */
    public function subscribe(UserId $userId, CollectionId $collectionId, DateTimeImmutable $addedAt): void;

    public function unsubscribe(UserId $userId, CollectionId $collectionId): void;
}
