<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\EnrichmentTargetView;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use Illuminate\Support\Facades\DB;

final class EloquentEnrichmentTargetReader implements EnrichmentTargetReader
{
    public function __construct(
        private readonly TranslationPick $pick = new TranslationPick(),
        private readonly LexicalNormalizer $normalizer = new LexicalNormalizer(),
    ) {}

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

    public function byIds(array $termIds, string $lang): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // Every term's text and language, once — the table is a few hundred rows, and this is the
        // only way to find SIBLINGS: other terms whose text collides with one of these once
        // normalised (a case/whitespace-only duplicate; the global `terms` unique index does not
        // normalise, so it does not catch this). A distractor already written or suppressed for one
        // sibling has to count as "already proposed" for the rest too, or a re-run offers the
        // identical wrong sentence to a term that only spells the same phrase differently — 8 live
        // instances found by the topup-3 proofreading pass (2026-08-19,
        // docs/enrich-v1-topup3-full-store.md).
        $textById = [];
        $langById = [];
        $idsByNormalizedText = [];
        foreach (DB::table('terms')->get(['id', 'text', 'lang']) as $t) {
            $tid = (string) $t->id;
            $textById[$tid] = (string) $t->text;
            $langById[$tid] = (string) $t->lang;
            $idsByNormalizedText[$this->normalizer->normalize((string) $t->text)][] = $tid;
        }

        $siblingsPerId = [];
        foreach ($ids as $id) {
            $key = $this->normalizer->normalize($textById[$id] ?? '');
            $siblingsPerId[$id] = array_values(array_filter(
                $idsByNormalizedText[$key] ?? [],
                static fn (string $sid): bool => $sid !== $id,
            ));
        }
        // Only the DISTRACTOR dedup reads this wider set (own rows + every sibling's); variants,
        // translation and the exported view itself stay scoped to $ids — a sibling's accepted forms
        // or translation are not this term's answer key.
        $dedupIds = array_values(array_unique(array_merge($ids, ...array_values($siblingsPerId))));

        // The PINNED example — `orderBy('id')`, the same rule EloquentTermContentReader and
        // EloquentTermAnswerKeyReader use. Distractors are keyed to `example_id`, so if this read
        // picked a different row than the card does, the whole product would point at a sentence
        // the learner never sees.
        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $dedupIds)->orderBy('id')
            ->get(['id', 'term_id', 'sentence']) as $row) {
            $examples[(string) $row->term_id] ??= $row;
        }

        // The translation the станок shows the model has to be the one in the COLLECTION's language:
        // it decides the whole shape of the prompt (which native-speaker interference the distractors
        // should imitate), and asking about a Ukrainian row while telling the model "Russian learner"
        // is a prompt describing content that isn't there. Deterministic, and the actual label of the
        // row that won travels on — TranslationPick may have had to fall back to another language,
        // and the brief must say so honestly rather than assert the language we asked for.
        $picked = $this->pick->forTerms($ids, $lang);

        // The example's gloss travels for the same reason and by the same rule — the model is shown
        // the sentence and what it means to THIS learner, so the two halves of the brief have to be
        // in one language or the prompt describes content that isn't there.
        $exampleTranslations = (new ExampleTranslationPick())->textsFor(
            array_values(array_map(static fn (object $e): string => (string) $e->id, $examples)),
            $lang,
        );

        // Variants an earlier run already accepted: part of the answer key from now on.
        $variants = [];
        foreach (DB::table('term_accepted_variants')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text']) as $row) {
            $variants[(string) $row->term_id][] = (string) $row->text;
        }

        // Near-synonyms an earlier run already stored. Scoped to $ids, like the variants above and
        // for the same reason: a sibling's synonyms are not this term's.
        $synonyms = [];
        foreach (DB::table('term_synonyms')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text']) as $row) {
            $synonyms[(string) $row->term_id][] = (string) $row->text;
        }

        // Distractors an earlier run already wrote against the pinned example. Read by example id, so
        // a term whose pinned example changed does not drag the old sentences along. Covers siblings
        // too, per $dedupIds above.
        $distractors = [];
        $pinnedIds = array_values(array_map(static fn (object $row): string => (string) $row->id, $examples));
        if ($pinnedIds !== []) {
            foreach (DB::table('example_distractors')->whereIn('example_id', $pinnedIds)->orderBy('id')
                ->get(['example_id', 'sentence']) as $row) {
                $distractors[(string) $row->example_id][] = (string) $row->sentence;
            }
        }

        // Sentences a human (review) or the retro-audit already judged wrong for this term and removed.
        // Folded into `existingDistractors` below — the validator's dedup treats "already rejected" the
        // same as "already stored", so the станок never proposes the same sentence back on a топап.
        // Covers siblings too, per $dedupIds above.
        $suppressed = [];
        foreach (DB::table('enrichment_suppressions')->whereIn('term_id', $dedupIds)->get(['term_id', 'sentence']) as $row) {
            $suppressed[(string) $row->term_id][] = (string) $row->sentence;
        }

        $out = [];
        foreach ($ids as $id) {
            $text = $textById[$id] ?? null;
            if ($text === null) {
                continue;
            }
            $example = $examples[$id] ?? null;

            $siblingSentences = [];
            foreach ($siblingsPerId[$id] as $sibling) {
                $sibExample = $examples[$sibling] ?? null;
                if ($sibExample !== null) {
                    array_push($siblingSentences, ...($distractors[(string) $sibExample->id] ?? []));
                }
                array_push($siblingSentences, ...($suppressed[$sibling] ?? []));
            }

            $out[$id] = new EnrichmentTargetView(
                termId: $id,
                text: $text,
                acceptedForms: [$text, ...($variants[$id] ?? [])],
                translation: $picked[$id]['text'] ?? null,
                exampleId: $example !== null ? (string) $example->id : null,
                exampleSentence: $example !== null && $example->sentence !== null ? (string) $example->sentence : null,
                exampleTranslation: $example !== null ? ($exampleTranslations[(string) $example->id] ?? null) : null,
                lang: $langById[$id] ?? 'en',
                translationLang: $picked[$id]['lang'] ?? null,
                existingDistractors: [
                    ...($example !== null ? ($distractors[(string) $example->id] ?? []) : []),
                    ...($suppressed[$id] ?? []),
                    ...$siblingSentences,
                ],
                existingSynonyms: $synonyms[$id] ?? [],
            );
        }

        return $out;
    }
}
