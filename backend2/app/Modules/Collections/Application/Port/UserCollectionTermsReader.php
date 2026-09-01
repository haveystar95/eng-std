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

    /**
     * WHEN each of a collection's terms joined it — the item's own `created_at`.
     *
     * A membership has a date and until now nobody asked for it. The plan's ladder does: a plan day
     * IS a collection written at one instant, so «when did this card join this plan» and «when was
     * this collection item created» are the same fact, and reading it here is what lets the ladder
     * be scoped to a plan without Learning storing a second copy of a date Collections already has
     * ({@see \App\Modules\Learning\Application\Service\PlanStandings}).
     *
     * A term whose row carries no timestamp (there are legacy items with a null `created_at`) is
     * absent from the map rather than dated `now` — «unknown» must read as «no cutoff», which
     * leaves the ladder exactly as wide as it was before this method existed.
     *
     * @return array<string, \DateTimeImmutable>  term id => the moment it joined this collection
     */
    public function joinedAtForCollection(UserId $userId, string $collectionId, int $limit): array;
}
