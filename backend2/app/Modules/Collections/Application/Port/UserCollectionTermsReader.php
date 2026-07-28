<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Read model letting Learning discover which terms live in a user's own collections,
 * without reaching into Collections' tables. Used to introduce not-yet-studied terms as
 * "new" study cards.
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
}
