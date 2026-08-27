<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use Illuminate\Support\Facades\DB;

/**
 * THE CEFR range of a collection, derived from its terms — one derivation, two readers.
 *
 * It is a read projection over the Vocabulary tables rather than a column, because the generator
 * never linked the requested levels back to the collection: what a deck's level IS, is what its
 * words turned out to be.
 *
 * 'A1'..'C2' order lexicographically, so string MIN/MAX give the true CEFR bounds. Collapses to a
 * single level when min == max; terms with no level are skipped; a deck where none carry one is
 * absent from the map, and the caller prints nothing — «уровень —» is not a level.
 *
 * Extracted the day the home screen's shop window needed the same badge the store screen draws
 * ({@see StoreCatalogueItem}). A second copy of this query is how «A2–B1» on one screen becomes
 * «A2» on another for the same deck.
 */
final class CollectionLevels
{
    /**
     * @param  list<string>  $collectionIds
     * @return array<string, string>  collection id → level or range, absent when no term carries one
     */
    public function forCollections(array $collectionIds): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $rows = DB::table('collection_items as ci')
            ->join('terms as t', 't.id', '=', 'ci.term_id')
            ->whereIn('ci.collection_id', $collectionIds)
            ->whereNull('ci.deleted_at')
            ->whereNotNull('t.cefr')
            ->groupBy('ci.collection_id')
            ->selectRaw('ci.collection_id as cid, min(t.cefr) as lo, max(t.cefr) as hi')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $lo = (string) $row->lo;
            $hi = (string) $row->hi;
            $map[(string) $row->cid] = $lo === $hi ? $lo : $lo . '–' . $hi;
        }

        return $map;
    }
}
