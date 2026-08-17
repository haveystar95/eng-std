<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\EnrichmentFindingRow;
use App\Modules\Admin\Application\Dto\ExampleDistractorRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\TermDetail;
use App\Modules\Admin\Application\Dto\TermExampleRow;
use App\Modules\Admin\Application\Dto\TermRow;
use App\Modules\Admin\Application\Dto\TermTranslationRow;
use App\Modules\Admin\Application\Dto\TermVariantRow;
use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use App\Modules\Admin\Infrastructure\Support\Keyset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Term list/detail projection over the Vocabulary tables (+ a progress-footprint count). */
final class EloquentAdminTermReader implements AdminTermReader
{
    public function list(?string $search, ListWindow $window): Page
    {
        // Retired terms stay in the table (the review log points at them) but are not dictionary
        // any more, so the list must not offer them.
        $base = DB::table('terms')->whereNull('deleted_at');
        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $base->where(function (Builder $q) use ($like): void {
                $q->where('text', 'ILIKE', $like)->orWhere('normalized_text', 'ILIKE', $like);
            });
        }

        return Keyset::page(
            $base,
            $window,
            'id',
            ['id', 'lang', 'text', 'type', 'created_at'],
            function (array $rows): array {
                $termIds = array_map(static fn (stdClass $r): string => (string) $r->id, $rows);
                $translations = $this->primaryTranslations($termIds);

                return array_map(static fn (stdClass $r): TermRow => new TermRow(
                    id: (string) $r->id,
                    lang: (string) $r->lang,
                    text: (string) $r->text,
                    type: (string) $r->type,
                    translation: $translations[(string) $r->id] ?? null,
                    createdAt: Iso::orNull($r->created_at),
                ), $rows);
            },
        );
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

        // Ordered by id: the FIRST row is the pinned example — the one the card and the client
        // show, and the only one distractors hang off (see EloquentTermContentReader).
        $examples = DB::table('term_examples')
            ->where('term_id', $termId)
            ->orderBy('id')
            ->get(['id', 'sentence', 'sentence_translation'])
            ->values()
            ->all();

        $collections = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('ci.term_id', $termId)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->get(['c.id', 'c.title', 'c.type']);

        $progressCount = DB::table('user_term_progress')->where('term_id', $termId)->count();

        $distractors = $this->distractorsByExample(array_values(array_map(
            static fn (stdClass $r): string => (string) $r->id,
            $examples,
        )));

        return new TermDetail(
            id: (string) $t->id,
            lang: (string) $t->lang,
            text: (string) $t->text,
            normalizedText: (string) $t->normalized_text,
            type: (string) $t->type,
            pos: $t->pos !== null ? (string) $t->pos : null,
            ipa: $t->ipa !== null ? (string) $t->ipa : null,
            audioUrl: $t->audio_url !== null ? (string) $t->audio_url : null,
            imageUrl: $t->image_url !== null ? (string) $t->image_url : null,
            imageAuthor: $t->image_author !== null ? (string) $t->image_author : null,
            imageAuthorUrl: $t->image_author_url !== null ? (string) $t->image_author_url : null,
            cefr: $t->cefr !== null ? (string) $t->cefr : null,
            source: (string) $t->source,
            createdAt: Iso::orNull($t->created_at),
            updatedAt: Iso::orNull($t->updated_at),
            translations: array_values(array_map(static fn (stdClass $r): TermTranslationRow => new TermTranslationRow(
                lang: (string) $r->lang,
                text: (string) $r->text,
                isPrimary: (bool) $r->is_primary,
            ), $translations->all())),
            examples: array_map(
                static fn (stdClass $r, int $i): TermExampleRow => new TermExampleRow(
                    id: (string) $r->id,
                    sentence: (string) $r->sentence,
                    translation: $r->sentence_translation !== null ? (string) $r->sentence_translation : null,
                    isPinned: $i === 0,
                    distractors: $distractors[(string) $r->id] ?? [],
                ),
                $examples,
                array_keys($examples),
            ),
            collections: array_values(array_map(static fn (stdClass $r): CollectionRefRow => new CollectionRefRow(
                id: (string) $r->id,
                title: (string) $r->title,
                type: (string) $r->type,
            ), $collections->all())),
            acceptedVariants: $this->acceptedVariants($termId),
            findings: $this->findings($termId),
            enrichmentVersion: $this->enrichmentVersion($termId),
            progressCount: $progressCount,
        );
    }

    /**
     * @param  list<string>  $exampleIds
     * @return array<string, list<ExampleDistractorRow>>
     */
    private function distractorsByExample(array $exampleIds): array
    {
        if ($exampleIds === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('example_distractors')
            ->whereIn('example_id', $exampleIds)
            ->orderBy('id')
            ->get(['id', 'example_id', 'sentence', 'error_type', 'error_span', 'correction', 'generator_version']);

        foreach ($rows as $r) {
            $out[(string) $r->example_id][] = new ExampleDistractorRow(
                id: (string) $r->id,
                sentence: (string) $r->sentence,
                errorType: (string) $r->error_type,
                errorSpan: (string) $r->error_span,
                correction: (string) $r->correction,
                generatorVersion: (string) $r->generator_version,
            );
        }

        return $out;
    }

    /** @return list<TermVariantRow> */
    private function acceptedVariants(string $termId): array
    {
        $rows = DB::table('term_accepted_variants')
            ->where('term_id', $termId)
            ->orderBy('id')
            ->get(['text', 'note', 'generator_version']);

        return array_values(array_map(static fn (stdClass $r): TermVariantRow => new TermVariantRow(
            text: (string) $r->text,
            note: $r->note !== null ? (string) $r->note : null,
            generatorVersion: (string) $r->generator_version,
        ), $rows->all()));
    }

    /** @return list<EnrichmentFindingRow> */
    private function findings(string $termId): array
    {
        $rows = DB::table('enrichment_findings')
            ->where('term_id', $termId)
            ->orderByDesc('id')
            ->get(['kind', 'field', 'detail', 'generator_version', 'created_at']);

        return array_values(array_map(static fn (stdClass $r): EnrichmentFindingRow => new EnrichmentFindingRow(
            kind: (string) $r->kind,
            field: $r->field !== null ? (string) $r->field : null,
            detail: (string) $r->detail,
            generatorVersion: (string) $r->generator_version,
            createdAt: Iso::orNull($r->created_at),
        ), $rows->all()));
    }

    private function enrichmentVersion(string $termId): ?string
    {
        $row = DB::table('term_enrichment_versions')
            ->where('term_id', $termId)
            ->orderByDesc('created_at')
            ->first(['generator_version']);

        return $row !== null ? (string) $row->generator_version : null;
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
