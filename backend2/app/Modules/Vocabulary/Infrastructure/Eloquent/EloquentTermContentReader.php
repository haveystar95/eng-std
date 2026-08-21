<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermContentReader implements TermContentReader
{
    public function __construct(private readonly TranslationPick $pick = new TranslationPick()) {}

    public function byIds(array $termIds, string $lang): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // The learner's own language decides which translation is the question on this card, and the
        // pick is deterministic — see TranslationPick. Before it, this was `orderByDesc('is_primary')`
        // with no language at all, which is how a term carrying a Ukrainian row could ask a
        // Russian-speaking learner in Ukrainian.
        $translations = $this->pick->forTerms($ids, $lang);

        // A term may hold several examples (ImportTerm appends one per generation pass), but a card
        // shows exactly one — so which one must be PINNED, not whichever the heap hands back. Without
        // an explicit order an unordered scan can return a different row after any UPDATE to the
        // table, i.e. the same term would show a different example between two requests, and the
        // client (which mirrors one example per term via /sync) would disagree with the card the
        // server built. `id` is a ULID, so ordering by it pins the term's FIRST example and keeps
        // the same one for good. Same order as EloquentExampleRegenContextReader, so "New example"
        // replaces the example the user is actually looking at.
        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')->get() as $row) {
            $examples[(string) $row->term_id] ??= $row;
        }

        // The device grades typed answers offline against {text ∪ variants}, so the variants travel
        // with the term. Without them the client rejects an answer the server accepts, and the
        // learner sees «Не то» on a card the scheduler then counts as correct.
        $variants = [];
        foreach (DB::table('term_accepted_variants')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text']) as $row) {
            $variants[(string) $row->term_id][] = (string) $row->text;
        }

        // The description is written in the language BEING LEARNED — it is the question of the
        // description_match card, not a gloss for the learner's own language, so it is picked by
        // the TERM's language and not by `$lang`.
        $descriptions = [];
        foreach (DB::table('term_descriptions')->whereIn('term_id', $ids)->get(['term_id', 'lang', 'text']) as $row) {
            $descriptions[(string) $row->term_id][(string) $row->lang] = (string) $row->text;
        }

        // Distractors hang off the PINNED example's id, so only that example's rows are shipped.
        $exampleIds = [];
        foreach ($examples as $termId => $row) {
            $exampleIds[(string) $row->id] = (string) $termId;
        }
        $distractors = [];
        if ($exampleIds !== []) {
            foreach (DB::table('example_distractors')->whereIn('example_id', array_keys($exampleIds))->orderBy('id')
                ->get(['example_id', 'sentence', 'error_type', 'error_span', 'correction']) as $row) {
                $distractors[$exampleIds[(string) $row->example_id]][] = [
                    'sentence' => (string) $row->sentence,
                    'error_type' => (string) $row->error_type,
                    'error_span' => (string) $row->error_span,
                    'correction' => (string) $row->correction,
                ];
            }
        }

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get() as $term) {
            $id = (string) $term->id;
            $example = $examples[$id] ?? null;

            $out[$id] = new TermContentView(
                id: $id,
                lang: (string) $term->lang,
                text: (string) $term->text,
                type: (string) $term->type,
                transcription: $term->ipa !== null ? (string) $term->ipa : null,
                translation: $translations[$id]['text'] ?? null,
                example: $example !== null && $example->sentence !== null ? (string) $example->sentence : null,
                exampleTranslation: $example !== null && $example->sentence_translation !== null ? (string) $example->sentence_translation : null,
                description: $descriptions[$id][(string) $term->lang] ?? null,
                imageUrl: $term->image_url !== null ? (string) $term->image_url : null,
                imageAuthor: $term->image_author !== null ? (string) $term->image_author : null,
                imageAuthorUrl: $term->image_author_url !== null ? (string) $term->image_author_url : null,
                acceptedVariants: $variants[$id] ?? [],
                exampleDistractors: $distractors[$id] ?? [],
            );
        }

        return $out;
    }
}
