<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Read model letting Learning discover which terms live in a user's collections — the ones they
 * OWN plus the store collections they are actively subscribed to (owner ∪ active subscription) —
 * without reaching into Collections' tables. Used to introduce not-yet-studied terms as "new"
 * cards, to scope a session (study or practice) to one collection, and to derive per-collection
 * progress. An unsubscribe (tombstone) removes access here too.
 */
interface UserCollectionTermsReader
{
    /**
     * Distinct term ids across the user's accessible (non-deleted) collections — owned ∪ actively
     * subscribed — in study order (oldest collection first, then item position).
     *
     * @return list<string>
     */
    public function termIdsForUser(UserId $userId, int $limit): array;

    /**
     * Term ids of a single collection the user can study (owns it, or is actively subscribed to it),
     * in item position order. Empty if the collection is missing, deleted, or not accessible.
     *
     * @return list<string>
     */
    public function termIdsForCollection(UserId $userId, string $collectionId, int $limit): array;

    /**
     * Term ids grouped by collection for the user's accessible (non-deleted) collections —
     * owned ∪ actively subscribed.
     *
     * @return array<string, list<string>>  collection id => term ids
     */
    public function termIdsByCollection(UserId $userId): array;
}
