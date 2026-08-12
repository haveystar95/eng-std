<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\EnrichmentTargetView;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use Illuminate\Support\Facades\DB;

final class EloquentEnrichmentTargetReader implements EnrichmentTargetReader
{
    public function underCovered(array $termIds, int $minDistractors): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // The PINNED example only — the one the card shows and the one distractors hang off.
        $pinned = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')->get(['id', 'term_id']) as $row) {
            $pinned[(string) $row->term_id] ??= (string) $row->id;
        }
        if ($pinned === []) {
            return [];
        }

        $counts = [];
        foreach (DB::table('example_distractors')
            ->whereIn('example_id', array_values($pinned))
            ->groupBy('example_id')
            ->selectRaw('example_id, count(*) AS n')
            ->get() as $row) {
            $counts[(string) $row->example_id] = (int) $row->n;
        }

        $out = [];
        foreach ($ids as $termId) {
            $exampleId = $pinned[$termId] ?? null;
            if ($exampleId === null) {
                continue;
            }
            if (($counts[$exampleId] ?? 0) < $minDistractors) {
                $out[] = $termId;
            }
        }

        return $out;
    }

    public function byIds(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // The PINNED example — `orderBy('id')`, the same rule EloquentTermContentReader and
        // EloquentTermAnswerKeyReader use. Distractors are keyed to `example_id`, so if this read
        // picked a different row than the card does, the whole product would point at a sentence
        // the learner never sees.
        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')
            ->get(['id', 'term_id', 'sentence', 'sentence_translation']) as $row) {
            $examples[(string) $row->term_id] ??= $row;
        }

        // The primary translation carries the LEARNER's language, which is what decides the whole
        // shape of the prompt (which native-speaker interference the distractors should imitate).
        $translations = [];
        $translationLangs = [];
        foreach (DB::table('term_translations')->whereIn('term_id', $ids)->orderByDesc('is_primary')
            ->get(['term_id', 'text', 'lang']) as $row) {
            $termId = (string) $row->term_id;
            if (! isset($translations[$termId])) {
                $translations[$termId] = (string) $row->text;
                $translationLangs[$termId] = (string) $row->lang;
            }
        }

        // Variants an earlier run already accepted: part of the answer key from now on.
        $variants = [];
        foreach (DB::table('term_accepted_variants')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text']) as $row) {
            $variants[(string) $row->term_id][] = (string) $row->text;
        }

        // Distractors an earlier run already wrote against the pinned example. Read by example id, so
        // a term whose pinned example changed does not drag the old sentences along.
        $distractors = [];
        $pinnedIds = array_values(array_map(static fn (object $row): string => (string) $row->id, $examples));
        if ($pinnedIds !== []) {
            foreach (DB::table('example_distractors')->whereIn('example_id', $pinnedIds)->orderBy('id')
                ->get(['example_id', 'sentence']) as $row) {
                $distractors[(string) $row->example_id][] = (string) $row->sentence;
            }
        }

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get(['id', 'text', 'lang']) as $term) {
            $id = (string) $term->id;
            $example = $examples[$id] ?? null;

            $out[$id] = new EnrichmentTargetView(
                termId: $id,
                text: (string) $term->text,
                acceptedForms: [(string) $term->text, ...($variants[$id] ?? [])],
                translation: $translations[$id] ?? null,
                exampleId: $example !== null ? (string) $example->id : null,
                exampleSentence: $example !== null && $example->sentence !== null ? (string) $example->sentence : null,
                exampleTranslation: $example !== null && $example->sentence_translation !== null
                    ? (string) $example->sentence_translation
                    : null,
                lang: (string) $term->lang,
                translationLang: $translationLangs[$id] ?? null,
                existingDistractors: $example !== null ? ($distractors[(string) $example->id] ?? []) : [],
            );
        }

        return $out;
    }
}
