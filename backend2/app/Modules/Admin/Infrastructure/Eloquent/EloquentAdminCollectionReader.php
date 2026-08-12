<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\CollectionDetail;
use App\Modules\Admin\Application\Dto\CollectionRow;
use App\Modules\Admin\Application\Dto\CollectionTermRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Port\AdminCollectionReader;
use App\Modules\Admin\Application\Port\AdminCostReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use App\Modules\Admin\Infrastructure\Support\Keyset;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Collections list/detail projection. Term text/translation is hydrated from Vocabulary separately. */
final class EloquentAdminCollectionReader implements AdminCollectionReader
{
    private const TERMS_CAP = 500;

    public function __construct(private readonly AdminCostReader $costs) {}

    public function list(?string $type, ?string $search, ListWindow $window): Page
    {
        // Owner joined in rather than resolved per row: the list shows an email, and an id column
        // nobody can read is the reason you end up opening every collection to find one.
        $base = DB::table('collections as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.owner_id')
            ->whereNull('c.deleted_at');
        if ($type !== null && $type !== '') {
            $base->where('c.type', $type);
        }
        if ($search !== null && $search !== '') {
            $base->where('c.title', 'ILIKE', '%' . $search . '%');
        }

        return Keyset::page(
            $base,
            $window,
            'c.id',
            ['c.id', 'c.type', 'c.title', 'c.owner_id', 'c.source', 'c.items_count', 'c.created_at', 'u.email as owner_email'],
            function (array $rows): array {
                $costs = $this->costs->totalsForCollections(array_map(
                    static fn (stdClass $r): string => (string) $r->id,
                    $rows,
                ));

                return array_map(
                    static fn (stdClass $r): CollectionRow => new CollectionRow(
                        id: (string) $r->id,
                        type: (string) $r->type,
                        title: (string) $r->title,
                        ownerId: $r->owner_id !== null ? (string) $r->owner_id : null,
                        source: (string) $r->source,
                        itemsCount: (int) $r->items_count,
                        createdAt: Iso::orNull($r->created_at),
                        ownerEmail: $r->owner_email !== null ? (string) $r->owner_email : null,
                        costUsd: $costs[(string) $r->id] ?? 0.0,
                    ),
                    $rows,
                );
            },
        );
    }

    public function detail(string $collectionId): ?CollectionDetail
    {
        $c = DB::table('collections')->where('id', $collectionId)->first();
        if ($c === null) {
            return null;
        }

        $items = DB::table('collection_items')
            ->where('collection_id', $collectionId)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->limit(self::TERMS_CAP)
            ->get(['term_id', 'position']);

        $termIds = array_values(array_map(static fn (stdClass $r): string => (string) $r->term_id, $items->all()));
        $texts = $this->termTexts($termIds);
        $translations = $this->primaryTranslations($termIds);

        $terms = array_map(static fn (stdClass $r): CollectionTermRow => new CollectionTermRow(
            termId: (string) $r->term_id,
            text: $texts[(string) $r->term_id] ?? '',
            translation: $translations[(string) $r->term_id] ?? null,
            position: (int) $r->position,
        ), $items->all());

        return new CollectionDetail(
            id: (string) $c->id,
            type: (string) $c->type,
            title: (string) $c->title,
            description: $c->description !== null ? (string) $c->description : null,
            topic: $c->topic !== null ? (string) $c->topic : null,
            ownerId: $c->owner_id !== null ? (string) $c->owner_id : null,
            source: (string) $c->source,
            sourceLang: (string) $c->source_lang,
            targetLang: (string) $c->target_lang,
            itemsCount: (int) $c->items_count,
            createdAt: Iso::orNull($c->created_at),
            terms: array_values($terms),
        );
    }

    /**
     * @param  list<string>  $termIds
     * @return array<string, string>
     */
    private function termTexts(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        /** @var array<string, string> $map */
        $map = DB::table('terms')->whereIn('id', $termIds)->pluck('text', 'id')
            ->map(static fn ($t): string => (string) $t)->all();

        return $map;
    }

    /**
     * The primary translation text per term (falls back to any one when none is flagged primary).
     *
     * @param  list<string>  $termIds
     * @return array<string, string>
     */
    private function primaryTranslations(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('term_translations')
            ->whereIn('term_id', $termIds)
            ->orderByDesc('is_primary')
            ->get(['term_id', 'text', 'is_primary']);
        foreach ($rows as $r) {
            $tid = (string) $r->term_id;
            if (! isset($out[$tid])) {
                $out[$tid] = (string) $r->text; // first seen wins; primary sorts first
            }
        }

        return $out;
    }
}
