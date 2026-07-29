<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Read model letting Learning discover which terms live in a user's own collections,
 * without reaching into Collections' tables — used to introduce not-yet-studied terms as
 * "new" cards, to scope a session to one collection, and to derive per-collection progress.
 */
interface UserCollectionTermsReader
{
    /**
     * Distinct term ids across the user's own (non-deleted) collections, in study order
     * (oldest collection first, then item position).
     *
     * @return list<string>
     */
    public function termIdsForUser(UserId $userId, int $limit): array;

    /**
     * Term ids of a single collection the user owns (in item position order). Empty if the
     * collection is missing, deleted, or not owned by the user.
     *
     * @return list<string>
     */
    public function termIdsForCollection(UserId $userId, string $collectionId, int $limit): array;

    /**
     * Term ids grouped by collection for the user's own (non-deleted) collections.
     *
     * @return array<string, list<string>>  collection id => term ids
     */
    public function termIdsByCollection(UserId $userId): array;
}
