<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\ContentLabelCount;
use App\Modules\Admin\Application\Dto\ContentTermFacts;
use App\Modules\Admin\Application\Port\AdminContentHealthReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The content tables as a read projection. SELECT only — no branch of this class writes.
 *
 * The pinned example is `orderBy('id')` + first-wins, the SAME rule
 * {@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermContentReader} uses to decide
 * which sentence the learner is shown. Picking a different one here would make the whole report
 * describe a sentence nobody sees.
 */
final class EloquentAdminContentHealthReader implements AdminContentHealthReader
{
    public function termFacts(?string $collectionId = null): array
    {
        $terms = DB::table('terms')
            ->whereNull('deleted_at')
            ->when($collectionId !== null, fn ($q) => $q->whereIn('id', $this->termIdsOf((string) $collectionId)))
            ->orderBy('id')
            ->get(['id', 'text', 'type'])
            ->all();

        return $this->hydrate(array_values($terms));
    }

    public function termFactsById(string $termId): ?ContentTermFacts
    {
        $term = DB::table('terms')->whereNull('deleted_at')->where('id', $termId)->first(['id', 'text', 'type']);
        if ($term === null) {
            return null;
        }

        return $this->hydrate([$term])[0] ?? null;
    }

    public function collections(): array
    {
        $rows = DB::table('collections')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title', 'type']);

        return array_values(array_map(static fn (stdClass $r): CollectionRefRow => new CollectionRefRow(
            id: (string) $r->id,
            title: (string) $r->title,
            type: (string) $r->type,
        ), $rows->all()));
    }

    public function collection(string $collectionId): ?CollectionRefRow
    {
        $row = DB::table('collections')->whereNull('deleted_at')->where('id', $collectionId)
            ->first(['id', 'title', 'type']);

        return $row === null ? null : new CollectionRefRow(
            id: (string) $row->id,
            title: (string) $row->title,
            type: (string) $row->type,
        );
    }

    public function suppressionsBySource(): array
    {
        $rows = DB::table('enrichment_suppressions')
            ->groupBy('source')
            ->orderBy('source')
            ->selectRaw('source, count(*) AS n')
            ->get();

        return array_values(array_map(static fn (stdClass $r): ContentLabelCount => new ContentLabelCount(
            label: (string) $r->source,
            count: (int) $r->n,
        ), $rows->all()));
    }

    public function suppressionsForTerm(string $termId): array
    {
        $rows = DB::table('enrichment_suppressions')
            ->where('term_id', $termId)
            ->orderByDesc('created_at')
            ->get(['sentence', 'source', 'created_at']);

        return array_values(array_map(static fn (stdClass $r): array => [
            'sentence' => (string) $r->sentence,
            'source' => (string) $r->source,
            'created_at' => Iso::orNull($r->created_at),
        ], $rows->all()));
    }

    public function rejectionsByField(): array
    {
        $rows = DB::table('generation_rejections')
            ->groupBy('field')
            ->orderBy('field')
            ->selectRaw('field, count(*) AS n')
            ->get();

        return array_values(array_map(static fn (stdClass $r): ContentLabelCount => new ContentLabelCount(
            label: (string) $r->field,
            count: (int) $r->n,
        ), $rows->all()));
    }

    public function enrichmentVersionsForTerm(string $termId): array
    {
        $rows = DB::table('term_enrichment_versions')
            ->where('term_id', $termId)
            ->orderByDesc('created_at')
            ->get(['generator_version', 'created_at']);

        return array_values(array_map(static fn (stdClass $r): array => [
            'version' => (string) $r->generator_version,
            'created_at' => Iso::orNull($r->created_at),
        ], $rows->all()));
    }

    /** @return list<string> */
    private function termIdsOf(string $collectionId): array
    {
        return array_values(array_map(
            static fn (stdClass $r): string => (string) $r->term_id,
            DB::table('collection_items')
                ->where('collection_id', $collectionId)
                ->whereNull('deleted_at')
                ->get(['term_id'])
                ->all(),
        ));
    }

    /**
     * @param  list<stdClass>  $terms  rows carrying id/text/type
     * @return list<ContentTermFacts>
     */
    private function hydrate(array $terms): array
    {
        if ($terms === []) {
            return [];
        }

        $ids = array_map(static fn (stdClass $r): string => (string) $r->id, $terms);

        $translations = $this->primaryTranslations($ids);
        $examples = $this->pinnedExamples($ids);
        $spans = $this->spansByExample(array_values(array_map(
            static fn (stdClass $e): string => (string) $e->id,
            $examples,
        )));
        $variants = $this->variantCounts($ids);
        $versions = $this->latestVersions($ids);
        $collections = $this->collectionIds($ids);

        $out = [];
        foreach ($terms as $term) {
            $id = (string) $term->id;
            $example = $examples[$id] ?? null;
            $exampleId = $example !== null ? (string) $example->id : null;

            $out[] = new ContentTermFacts(
                termId: $id,
                text: (string) $term->text,
                translation: $translations[$id] ?? null,
                type: (string) $term->type,
                exampleId: $exampleId,
                exampleSentence: $example !== null && $example->sentence !== null ? (string) $example->sentence : null,
                exampleTranslation: $example !== null && $example->sentence_translation !== null
                    ? (string) $example->sentence_translation
                    : null,
                distractorSpans: $exampleId !== null ? ($spans[$exampleId] ?? []) : [],
                variantCount: $variants[$id] ?? 0,
                enrichmentVersion: $versions[$id] ?? null,
                collectionIds: $collections[$id] ?? [],
            );
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, stdClass>
     */
    private function pinnedExamples(array $ids): array
    {
        $out = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')
            ->get(['id', 'term_id', 'sentence', 'sentence_translation']) as $row) {
            // First wins — the pinned example is the LOWEST id, exactly as the card reads it.
            $out[(string) $row->term_id] ??= $row;
        }

        return $out;
    }

    /**
     * @param  list<string>  $exampleIds
     * @return array<string, list<string>>
     */
    private function spansByExample(array $exampleIds): array
    {
        if ($exampleIds === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('example_distractors')->whereIn('example_id', $exampleIds)->orderBy('id')
            ->get(['example_id', 'error_span']) as $row) {
            $out[(string) $row->example_id][] = (string) $row->error_span;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function primaryTranslations(array $ids): array
    {
        $out = [];
        foreach (DB::table('term_translations')->whereIn('term_id', $ids)
            ->orderByDesc('is_primary')->get(['term_id', 'text']) as $row) {
            $out[(string) $row->term_id] ??= (string) $row->text;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function variantCounts(array $ids): array
    {
        $out = [];
        foreach (DB::table('term_accepted_variants')->whereIn('term_id', $ids)
            ->groupBy('term_id')->selectRaw('term_id, count(*) AS n')->get() as $row) {
            $out[(string) $row->term_id] = (int) $row->n;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function latestVersions(array $ids): array
    {
        $out = [];
        foreach (DB::table('term_enrichment_versions')->whereIn('term_id', $ids)
            ->orderByDesc('created_at')->get(['term_id', 'generator_version']) as $row) {
            $out[(string) $row->term_id] ??= (string) $row->generator_version;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, list<string>>
     */
    private function collectionIds(array $ids): array
    {
        $out = [];
        foreach (DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->whereIn('ci.term_id', $ids)
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->orderBy('c.id')
            ->get(['ci.term_id', 'ci.collection_id']) as $row) {
            $out[(string) $row->term_id][] = (string) $row->collection_id;
        }

        return $out;
    }
}
