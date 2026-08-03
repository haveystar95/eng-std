<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\CollectionItemSyncRow;
use App\Modules\Collections\Application\Dto\CollectionSyncRow;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Delta-sync reads over the user's OWNED collections (subscriptions aren't wired into any read
 * path yet). Each method returns changes with an effective timestamp in (since, upper]; `since`
 * null is a full snapshot (upserts only — a fresh client has nothing to delete). Ordered by
 * (timestamp, id) so an offset cursor pages deterministically.
 */
interface CollectionSyncReader
{
    /** @return list<CollectionSyncRow> */
    public function changedCollections(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;

    /** @return list<CollectionItemSyncRow> */
    public function changedItems(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;

    /**
     * Term ids currently in the user's owned collections (live items) — the scope for term sync.
     *
     * @return list<string>
     */
    public function liveTermIds(UserId $userId): array;
}
