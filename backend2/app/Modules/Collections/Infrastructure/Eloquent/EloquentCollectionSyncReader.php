<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\CollectionItemSyncRow;
use App\Modules\Collections\Application\Dto\CollectionSyncRow;
use App\Modules\Collections\Application\Port\CollectionSyncReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

// Raw queries on purpose: sync must SEE soft-deleted rows (they are the tombstones), which the
// SoftDeletes model scope would hide. `updated_at` is the effective timestamp — Laravel's soft
// delete bumps updated_at alongside deleted_at, so one column catches upserts and deletes.
final class EloquentCollectionSyncReader implements CollectionSyncReader
{
    public function changedCollections(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
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
            ->get(['id', 'deleted_at', 'updated_at', 'title', 'description', 'topic', 'source_lang', 'target_lang', 'items_count']);

        return array_values($rows->map(fn ($r): CollectionSyncRow => new CollectionSyncRow(
            id: (string) $r->id,
            deleted: $r->deleted_at !== null,
            updatedAt: new DateTimeImmutable((string) $r->updated_at),
            title: $r->title !== null ? (string) $r->title : null,
            description: $r->description !== null ? (string) $r->description : null,
            topic: $r->topic !== null ? (string) $r->topic : null,
            sourceLang: $r->source_lang !== null ? (string) $r->source_lang : null,
            targetLang: $r->target_lang !== null ? (string) $r->target_lang : null,
            itemsCount: (int) $r->items_count,
        ))->all());
    }

    public function changedItems(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
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

    public function liveTermIds(UserId $userId): array
    {
        return array_values(DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->distinct()
            ->pluck('ci.term_id')
            ->map(static fn ($id): string => (string) $id)
            ->all());
    }
}
