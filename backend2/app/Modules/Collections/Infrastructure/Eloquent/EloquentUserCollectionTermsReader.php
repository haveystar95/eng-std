<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentUserCollectionTermsReader implements UserCollectionTermsReader
{
    public function termIdsForUser(UserId $userId, int $limit): array
    {
        // Join is within Collections' own tables (collections + collection_items) — no
        // cross-module reach. A term may sit in several collections; keep the first
        // occurrence in study order. Fetch a headroom of rows so dedup still yields `limit`.
        $rows = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
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
        $rows = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.id', $collectionId)
            ->where('c.owner_id', $userId->value)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->orderBy('ci.position')
            ->limit($limit)
            ->pluck('ci.term_id');

        return array_values(array_unique(array_map(static fn ($id): string => (string) $id, $rows->all())));
    }

    public function termIdsByCollection(UserId $userId): array
    {
        $rows = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
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
}
