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

    /**
     * Whether the user has an ACTIVE subscription to the collection (a `user_collections` row with
     * no `unsubscribed_at` tombstone). The single-collection form of the "active subscription" rule
     * the sync feed and the study-term scope use — read it here rather than re-testing the column.
     */
    public function isActive(UserId $userId, CollectionId $collectionId): bool;
}
