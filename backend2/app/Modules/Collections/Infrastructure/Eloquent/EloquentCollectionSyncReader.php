<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\CollectionItemSyncRow;
use App\Modules\Collections\Application\Dto\CollectionSyncRow;
use App\Modules\Collections\Application\Dto\SubscribedTermRef;
use App\Modules\Collections\Application\Port\CollectionSyncReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

// Raw queries on purpose: sync must SEE soft-deleted rows (they are the tombstones), which the
// SoftDeletes model scope would hide. `updated_at` is the effective timestamp for OWNED rows —
// Laravel's soft delete bumps updated_at alongside deleted_at, so one column catches upserts and
// deletes.
//
// The feed is OWNED collections ∪ SUBSCRIBED store collections (user_collections, active rows),
// so a collection added from the store arrives at the client fully (collection + items + terms).
// For a subscribed row the effective timestamp is GREATEST(collection.updated_at, subscription
// added_at) — a fresh subscription pulls the whole collection into the delta even if the store
// collection itself hasn't changed since `since`; an unsubscribe stamps `unsubscribed_at`, which
// becomes the effective timestamp of a per-user collection tombstone (op=delete). Two extra
// queries total — never N+1 per subscription.
final class EloquentCollectionSyncReader implements CollectionSyncReader
{
    public function changedCollections(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        $owned = $this->ownedCollections($userId, $since, $upper);
        $subscribed = $this->subscribedCollections($userId, $since, $upper);

        // Owned wins on the (unlikely) chance a collection is both; then sort by (updatedAt, id).
        $byId = [];
        foreach ([...$owned, ...$subscribed] as $row) {
            $byId[$row->id] ??= $row;
        }
        $merged = array_values($byId);
        usort($merged, static fn (CollectionSyncRow $a, CollectionSyncRow $b): int => [$a->updatedAt, $a->id] <=> [$b->updatedAt, $b->id]);

        return $merged;
    }

    public function changedItems(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        $owned = $this->ownedItems($userId, $since, $upper);
        $subscribed = $this->subscribedItems($userId, $since, $upper);

        $byKey = [];
        foreach ([...$owned, ...$subscribed] as $row) {
            $byKey[$row->collectionId . '|' . $row->termId] ??= $row;
        }
        $merged = array_values($byKey);
        usort($merged, static fn (CollectionItemSyncRow $a, CollectionItemSyncRow $b): int => [$a->updatedAt, $a->collectionId, $a->termId] <=> [$b->updatedAt, $b->collectionId, $b->termId]);

        return $merged;
    }

    public function liveTermIds(UserId $userId): array
    {
        $owned = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->pluck('ci.term_id');

        $subscribed = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->join('user_collections as uc', 'uc.collection_id', '=', 'c.id')
            ->where('uc.user_id', $userId->value)
            ->whereNull('uc.unsubscribed_at')
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->pluck('ci.term_id');

        $ids = [];
        foreach ([...$owned->all(), ...$subscribed->all()] as $id) {
            $ids[(string) $id] = true;
        }

        return array_keys($ids);
    }

    public function recentlyRemovedTermIds(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        if ($since === null) {
            return []; // snapshot: nothing to tombstone
        }

        // Deliberately does NOT filter ci.deleted_at / c.deleted_at away — removed rows are the
        // whole point here. Both halves of the user's scope (owned + subscribed, subscription in
        // any state, since deleting a collection also ends its subscriptions).
        $owned = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
            ->whereNotNull('ci.deleted_at')
            ->where('ci.deleted_at', '>=', $since)
            ->where('ci.deleted_at', '<=', $upper)
            ->pluck('ci.term_id');

        $subscribed = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->join('user_collections as uc', 'uc.collection_id', '=', 'c.id')
            ->where('uc.user_id', $userId->value)
            ->whereNotNull('ci.deleted_at')
            ->where('ci.deleted_at', '>=', $since)
            ->where('ci.deleted_at', '<=', $upper)
            ->pluck('ci.term_id');

        $ids = [];
        foreach ([...$owned->all(), ...$subscribed->all()] as $id) {
            $ids[(string) $id] = true;
        }

        return array_keys($ids);
    }

    public function newlySubscribedTermRefs(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        // Snapshot already ships every live term (liveTermIds ∋ subscribed) — nothing extra to force.
        if ($since === null) {
            return [];
        }

        // Terms of collections whose subscription (re)activated in (since, upper]: their added_at is
        // the effective moment the term entered this user's scope. Dedup by term id, keeping the
        // latest added_at (a term shared across two just-subscribed decks appears once).
        $rows = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->join('user_collections as uc', 'uc.collection_id', '=', 'c.id')
            ->where('uc.user_id', $userId->value)
            ->whereNull('uc.unsubscribed_at')
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->where('uc.added_at', '>=', $since) // inclusive, matching the collection/item windows
            ->where('uc.added_at', '<=', $upper)
            ->groupBy('ci.term_id')
            ->selectRaw('ci.term_id, MAX(uc.added_at) as effective_at')
            ->orderBy('effective_at')->orderBy('ci.term_id')
            ->get();

        return array_values($rows->map(fn ($r): SubscribedTermRef => new SubscribedTermRef(
            id: (string) $r->term_id,
            updatedAt: new DateTimeImmutable((string) $r->effective_at),
        ))->all());
    }

    /** @return list<CollectionSyncRow> */
    private function ownedCollections(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        $q = DB::table('collections')
            ->where('owner_id', $userId->value)
            ->where('updated_at', '<=', $upper);
        if ($since !== null) {
            $q->where('updated_at', '>=', $since);
        } else {
            $q->whereNull('deleted_at'); // full snapshot: upserts only
        }

        $rows = $q->orderBy('updated_at')->orderBy('id')
            ->get(['id', 'deleted_at', 'updated_at', 'title', 'description', 'topic', 'source_lang', 'target_lang', 'items_count', 'source', 'type', 'image_url', 'image_author', 'image_author_url']);

        return array_values($rows->map(fn ($r): CollectionSyncRow => $this->toCollectionRow(
            id: (string) $r->id,
            deleted: $r->deleted_at !== null,
            effectiveAt: (string) $r->updated_at,
            r: $r,
        ))->all());
    }

    /** @return list<CollectionSyncRow> */
    private function subscribedCollections(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        // effective_at: active → GREATEST(collection change, subscription add); unsubscribed → the
        // unsubscribe instant (drives a per-user tombstone). unsub as 0/1 for portable boolean reads.
        $base = DB::table('collections as c')
            ->join('user_collections as uc', 'uc.collection_id', '=', 'c.id')
            ->where('uc.user_id', $userId->value)
            ->selectRaw(
                'c.id, c.title, c.description, c.topic, c.source_lang, c.target_lang, c.items_count, '
                . 'c.source, c.type, c.image_url, c.image_author, c.image_author_url, c.deleted_at, '
                . 'CASE WHEN uc.unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END as unsub, '
                . 'CASE WHEN uc.unsubscribed_at IS NULL THEN GREATEST(c.updated_at, uc.added_at) ELSE uc.unsubscribed_at END as effective_at'
            );

        $q = DB::query()->fromSub($base, 't')->where('t.effective_at', '<=', $upper);
        if ($since !== null) {
            $q->where('t.effective_at', '>=', $since);
        } else {
            $q->where('t.unsub', 0)->whereNull('t.deleted_at'); // snapshot: active upserts only
        }

        $rows = $q->orderBy('t.effective_at')->orderBy('t.id')->get();

        return array_values($rows->map(fn ($r): CollectionSyncRow => $this->toCollectionRow(
            id: (string) $r->id,
            deleted: (int) $r->unsub === 1 || $r->deleted_at !== null,
            effectiveAt: (string) $r->effective_at,
            r: $r,
        ))->all());
    }

    /** Shared mapping for owned + subscribed collection rows (same column set). */
    private function toCollectionRow(string $id, bool $deleted, string $effectiveAt, \stdClass $r): CollectionSyncRow
    {
        return new CollectionSyncRow(
            id: $id,
            deleted: $deleted,
            updatedAt: new DateTimeImmutable($effectiveAt),
            title: $r->title !== null ? (string) $r->title : null,
            description: $r->description !== null ? (string) $r->description : null,
            topic: $r->topic !== null ? (string) $r->topic : null,
            sourceLang: $r->source_lang !== null ? (string) $r->source_lang : null,
            targetLang: $r->target_lang !== null ? (string) $r->target_lang : null,
            itemsCount: (int) $r->items_count,
            source: $r->source !== null ? (string) $r->source : null,
            type: $r->type !== null ? (string) $r->type : null,
            imageUrl: $r->image_url !== null ? (string) $r->image_url : null,
            imageAuthor: $r->image_author !== null ? (string) $r->image_author : null,
            imageAuthorUrl: $r->image_author_url !== null ? (string) $r->image_author_url : null,
        );
    }

    /** @return list<CollectionItemSyncRow> */
    private function ownedItems(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        $q = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
            ->where('ci.updated_at', '<=', $upper);
        if ($since !== null) {
            $q->where('ci.updated_at', '>=', $since);
        } else {
            $q->whereNull('ci.deleted_at'); // full snapshot: live items only
        }

        $rows = $q->orderBy('ci.updated_at')->orderBy('ci.id')
            ->get(['ci.collection_id', 'ci.term_id', 'ci.deleted_at', 'ci.updated_at', 'ci.position', 'ci.note']);

        return array_values($rows->map(fn ($r): CollectionItemSyncRow => new CollectionItemSyncRow(
            collectionId: (string) $r->collection_id,
            termId: (string) $r->term_id,
            deleted: $r->deleted_at !== null,
            updatedAt: new DateTimeImmutable((string) $r->updated_at),
            position: (int) $r->position,
            note: $r->note !== null ? (string) $r->note : null,
        ))->all());
    }

    /** @return list<CollectionItemSyncRow> */
    private function subscribedItems(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        // Active subscriptions only: an unsubscribe removes the collection via its own tombstone, and
        // the client reconcile drops the items with it — no per-item tombstones needed. A fresh
        // subscription (added_at in-window) pulls every live item; store edits ride ci.updated_at.
        $base = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->join('user_collections as uc', 'uc.collection_id', '=', 'c.id')
            ->where('uc.user_id', $userId->value)
            ->whereNull('uc.unsubscribed_at')
            ->selectRaw(
                'ci.collection_id, ci.term_id, ci.deleted_at, ci.position, ci.note, '
                . 'GREATEST(ci.updated_at, uc.added_at) as effective_at'
            );

        $q = DB::query()->fromSub($base, 't')->where('t.effective_at', '<=', $upper);
        if ($since !== null) {
            $q->where('t.effective_at', '>=', $since);
        } else {
            $q->whereNull('t.deleted_at'); // snapshot: live items only
        }

        $rows = $q->orderBy('t.effective_at')->orderBy('t.term_id')->get();

        return array_values($rows->map(fn ($r): CollectionItemSyncRow => new CollectionItemSyncRow(
            collectionId: (string) $r->collection_id,
            termId: (string) $r->term_id,
            deleted: $r->deleted_at !== null,
            updatedAt: new DateTimeImmutable((string) $r->effective_at),
            position: (int) $r->position,
            note: $r->note !== null ? (string) $r->note : null,
        ))->all());
    }
}
