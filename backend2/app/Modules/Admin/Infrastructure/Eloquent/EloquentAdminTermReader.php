<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\TermDetail;
use App\Modules\Admin\Application\Dto\TermExampleRow;
use App\Modules\Admin\Application\Dto\TermRow;
use App\Modules\Admin\Application\Dto\TermTranslationRow;
use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Term list/detail projection over the Vocabulary tables (+ a progress-footprint count). */
final class EloquentAdminTermReader implements AdminTermReader
{
    public function list(?string $search, int $page, int $perPage): Page
    {
        $base = DB::table('terms');
        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $base->where(function (Builder $q) use ($like): void {
                $q->where('text', 'ILIKE', $like)->orWhere('normalized_text', 'ILIKE', $like);
            });
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderByDesc('created_at')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get(['id', 'lang', 'text', 'type', 'created_at']);

        $termIds = array_values(array_map(static fn (stdClass $r): string => (string) $r->id, $rows->all()));
        $translations = $this->primaryTranslations($termIds);

        $items = array_map(static fn (stdClass $r): TermRow => new TermRow(
            id: (string) $r->id,
            lang: (string) $r->lang,
            text: (string) $r->text,
            type: (string) $r->type,
            translation: $translations[(string) $r->id] ?? null,
            createdAt: Iso::orNull($r->created_at),
        ), $rows->all());

        return new Page(array_values($items), $total, $page, $perPage);
    }

    public function detail(string $termId): ?TermDetail
    {
        $t = DB::table('terms')->where('id', $termId)->first();
        if ($t === null) {
            return null;
        }

        $translations = DB::table('term_translations')
            ->where('term_id', $termId)
            ->orderByDesc('is_primary')
            ->get(['lang', 'text', 'is_primary']);

        $examples = DB::table('term_examples')
            ->where('term_id', $termId)
            ->orderBy('id')
            ->get(['sentence', 'sentence_translation']);

        $collections = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('ci.term_id', $termId)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->get(['c.id', 'c.title', 'c.type']);

        $progressCount = DB::table('user_term_progress')->where('term_id', $termId)->count();

        return new TermDetail(
            id: (string) $t->id,
            lang: (string) $t->lang,
            text: (string) $t->text,
            normalizedText: (string) $t->normalized_text,
            type: (string) $t->type,
            pos: $t->pos !== null ? (string) $t->pos : null,
            ipa: $t->ipa !== null ? (string) $t->ipa : null,
            audioUrl: $t->audio_url !== null ? (string) $t->audio_url : null,
            source: (string) $t->source,
            createdAt: Iso::orNull($t->created_at),
            translations: array_values(array_map(static fn (stdClass $r): TermTranslationRow => new TermTranslationRow(
                lang: (string) $r->lang,
                text: (string) $r->text,
                isPrimary: (bool) $r->is_primary,
            ), $translations->all())),
            examples: array_values(array_map(static fn (stdClass $r): TermExampleRow => new TermExampleRow(
                sentence: (string) $r->sentence,
                translation: $r->sentence_translation !== null ? (string) $r->sentence_translation : null,
            ), $examples->all())),
            collections: array_values(array_map(static fn (stdClass $r): CollectionRefRow => new CollectionRefRow(
                id: (string) $r->id,
                title: (string) $r->title,
                type: (string) $r->type,
            ), $collections->all())),
            progressCount: $progressCount,
        );
    }

    /**
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
            ->get(['term_id', 'text']);
        foreach ($rows as $r) {
            $tid = (string) $r->term_id;
            if (! isset($out[$tid])) {
                $out[$tid] = (string) $r->text;
            }
        }

        return $out;
    }
}
