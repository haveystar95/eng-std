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
}
