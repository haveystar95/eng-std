<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\CollectionPairView;
use App\Modules\Collections\Application\Port\CollectionPairReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentCollectionPairReader implements CollectionPairReader
{
    public function pairFor(string $collectionId): ?CollectionPairView
    {
        $row = DB::table('collections')
            ->where('id', $collectionId)
            ->whereNull('deleted_at')
            ->first(['source_lang', 'target_lang']);

        if ($row === null) {
            return null;
        }

        return new CollectionPairView(
            targetLang: (string) $row->target_lang,
            sourceLang: (string) $row->source_lang,
        );
    }

    public function supportLangByTerm(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        // Ordered by `c.id` — a ULID, so this is the term's OLDEST collection, i.e. the folder it
        // arrived in. The first row per term wins; see the port for why the tie-break is pinned
        // rather than left to the heap.
        $rows = $this->accessible(
            DB::table('collection_items as ci')->join('collections as c', 'c.id', '=', 'ci.collection_id'),
            $userId,
        )
            ->whereIn('ci.term_id', $termIds)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->orderBy('c.id')
            ->get(['ci.term_id', 'c.source_lang']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->term_id] ??= (string) $row->source_lang;
        }

        return $out;
    }

    /**
     * The same access rule as {@see EloquentUserCollectionTermsReader::accessible()} — owned ∪
     * actively subscribed — stated again here rather than shared, because the two readers are in
     * different query shapes and a shared private helper would have to live somewhere neither owns.
     * If a third copy ever appears, that is the moment to extract it.
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
