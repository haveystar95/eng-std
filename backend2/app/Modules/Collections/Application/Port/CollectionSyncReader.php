<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\CollectionItemSyncRow;
use App\Modules\Collections\Application\Dto\CollectionSyncRow;
use App\Modules\Collections\Application\Dto\SubscribedTermRef;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Delta-sync reads over the user's collections — those they OWN plus store collections they are
 * actively SUBSCRIBED to (user_collections). Each method returns changes with an effective
 * timestamp in (since, upper]; for a subscribed collection that timestamp is GREATEST(collection
 * updated_at, subscription added_at), so a fresh subscription pulls the whole collection in, and
 * an unsubscribe emits a per-user tombstone. `since` null is a full snapshot (upserts only — a
 * fresh client has nothing to delete). Ordered by (timestamp, id) so an offset cursor pages
 * deterministically.
 */
interface CollectionSyncReader
{
    /** @return list<CollectionSyncRow> */
    public function changedCollections(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;

    /** @return list<CollectionItemSyncRow> */
    public function changedItems(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;

    /**
     * Term ids currently in the user's owned + actively-subscribed collections (live items) — the
     * scope for term sync.
     *
     * @return list<string>
     */
    public function liveTermIds(UserId $userId): array;

    /**
     * Term ids that LEFT the user's scope in (since, upper] — their collection item was removed
     * (individually, by a term being retired, or by the whole collection being deleted).
     *
     * `liveTermIds` can't see these by definition, and without them a retired term would never be
     * offered to `changedTermIds`, so its tombstone would never reach the device: the word would
     * sit in the local mirror forever. Empty for a full snapshot.
     *
     * @return list<string>
     */
    public function recentlyRemovedTermIds(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;

    /**
     * Terms that must ship in a DELTA because a collection was (re)subscribed in (since, upper] —
     * their own `updated_at` is old (the store term didn't change), so `changedTermIds` alone would
     * miss them and the client would get a collection with no term content. Empty for a full
     * snapshot (which already ships every live term). Deduped by term id.
     *
     * @return list<SubscribedTermRef>
     */
    public function newlySubscribedTermRefs(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;
}
