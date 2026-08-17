<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentUserCollectionTermsReader implements UserCollectionTermsReader
{
    public function termIdsForUser(UserId $userId, int $limit): array
    {
        // Join is within Collections' own tables (collections + collection_items) — no
        // cross-module reach. A term may sit in several collections; keep the first
        // occurrence in study order. Fetch a headroom of rows so dedup still yields `limit`.
        $rows = $this->accessible(
            DB::table('collection_items as ci')->join('collections as c', 'c.id', '=', 'ci.collection_id'),
            $userId,
        )
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->orderBy('c.created_at')
            ->orderBy('ci.position')
            ->limit(max($limit * 4, $limit))
            ->pluck('ci.term_id');

        $seen = [];
        foreach ($rows as $termId) {
            $seen[(string) $termId] = true;
            if (count($seen) >= $limit) {
                break;
            }
        }

        return array_keys($seen);
    }

    public function termIdsForCollection(UserId $userId, string $collectionId, int $limit): array
    {
        $rows = $this->accessible(
            DB::table('collection_items as ci')->join('collections as c', 'c.id', '=', 'ci.collection_id'),
            $userId,
        )
            ->where('c.id', $collectionId)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->orderBy('ci.position')
            ->limit($limit)
            ->pluck('ci.term_id');

        return array_values(array_unique(array_map(static fn ($id): string => (string) $id, $rows->all())));
    }

    public function termIdsByCollection(UserId $userId): array
    {
        $rows = $this->accessible(
            DB::table('collection_items as ci')->join('collections as c', 'c.id', '=', 'ci.collection_id'),
            $userId,
        )
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->orderBy('ci.position')
            ->get(['ci.collection_id', 'ci.term_id']);

        /** @var array<string, list<string>> $map */
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->collection_id][] = (string) $row->term_id;
        }

        return $map;
    }

    /**
     * The one access rule for "collections the user studies": the collections they OWN, plus the
     * store collections they are ACTIVELY subscribed to (a user_collections row with no
     * `unsubscribed_at` tombstone). Applied to a query already joined to `collections as c`. Keeping
     * it in one place means practice, the due/new pool and per-collection progress agree, and an
     * unsubscribe closes access everywhere at once. Mirrors the sync feed's owned ∪ subscribed rule.
     */
    private function accessible(Builder $query, UserId $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('c.owner_id', $userId->value)
                ->orWhereExists(function (Builder $sub) use ($userId): void {
                    $sub->from('user_collections as uc')
                        ->whereColumn('uc.collection_id', 'c.id')
                        ->where('uc.user_id', $userId->value)
                        ->whereNull('uc.unsubscribed_at');
                });
        });
    }
}
