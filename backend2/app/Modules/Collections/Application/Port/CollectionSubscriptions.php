<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** The user↔collection library membership (user_collections). Store subscribe/unsubscribe. */
interface CollectionSubscriptions
{
    /**
     * Idempotent: subscribing an already-active collection is a no-op; re-subscribing a previously
     * unsubscribed one re-activates it (clears the tombstone) with a fresh `addedAt`.
     */
    public function subscribe(UserId $userId, CollectionId $collectionId, DateTimeImmutable $addedAt): void;

    /** Idempotent soft-unsubscribe: stamps `unsubscribed_at` (a per-user tombstone) — keeps the row. */
    public function unsubscribe(UserId $userId, CollectionId $collectionId, DateTimeImmutable $at): void;
}
