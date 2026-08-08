<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\PreviewTerm;
use App\Modules\Collections\Application\Dto\StoreCollectionPreview;
use App\Modules\Collections\Application\Port\StorePreviewReader;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Support\Facades\DB;

// Read projection over Collections + Vocabulary tables (raw, like the store level range): the store
// preview is a Collections concern but the term text/translation live in Vocabulary. The native
// translation is the collection's source_lang primary translation for each term.
final class EloquentStorePreviewReader implements StorePreviewReader
{
    public function preview(CollectionId $collectionId, int $limit): ?StoreCollectionPreview
    {
        $collection = DB::table('collections')
            ->where('id', $collectionId->value)
            ->whereNull('deleted_at')
            ->where(fn ($w) => $w->where('visibility', 'public')->orWhere('type', 'system'))
            ->first(['id', 'source_lang']);

        if ($collection === null) {
            return null; // not a store collection → caller 404s (private/custom stay hidden)
        }

        $total = DB::table('collection_items')
            ->where('collection_id', $collectionId->value)
            ->whereNull('deleted_at')
            ->count();

        $rows = DB::table('collection_items as ci')
            ->join('terms as t', 't.id', '=', 'ci.term_id')
            ->where('ci.collection_id', $collectionId->value)
            ->whereNull('ci.deleted_at')
            ->orderBy('ci.position')
            ->orderBy('ci.id')
            ->limit($limit)
            ->selectRaw(
                't.text, t.type, t.cefr, '
                . '(select tr.text from term_translations tr where tr.term_id = t.id and tr.lang = ? '
                . 'order by tr.is_primary desc limit 1) as translation',
                [(string) $collection->source_lang],
            )
            ->get();

        $terms = array_values($rows->map(fn ($r): PreviewTerm => new PreviewTerm(
            text: (string) $r->text,
            translation: $r->translation !== null ? (string) $r->translation : null,
            type: (string) $r->type,
            cefr: $r->cefr !== null ? (string) $r->cefr : null,
        ))->all());

        return new StoreCollectionPreview(
            collectionId: $collectionId->value,
            total: $total,
            terms: $terms,
        );
    }
}
