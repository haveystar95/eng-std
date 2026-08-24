<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\SupportLanguages;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermContentReader implements TermContentReader
{
    public function __construct(
        private readonly TranslationPick $pick = new TranslationPick(),
        private readonly ExampleTranslationPick $examplePick = new ExampleTranslationPick(),
    ) {}

    public function byIds(array $termIds, SupportLanguages $langs): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // The collection's SUPPORT language decides which translation is the question on this card,
        // and the pick is deterministic — see TranslationPick. Before it, this was
        // `orderByDesc('is_primary')` with no language at all, which is how a term carrying a
        // Ukrainian row could ask a Russian-speaking learner in Ukrainian.
        //
        // One query per DISTINCT language, not per term: a session mixing an `en→ru` deck with an
        // `en→uk` one asks twice, and the ordinary single-pair session still asks once.
        $translations = [];
        foreach ($langs->group($ids) as $lang => $groupIds) {
            $translations += $this->pick->forTerms($groupIds, $lang);
        }

        // EVERY reading this term has, in the language the pick actually landed on, ordered by the
        // pick's own rule — so the first entry is the row `translation` above holds and the rest are
        // its alternatives («цель», then «задача»). One list per term, not per language: the pick
        // has already chosen the language, including its explicit last-resort fallback, and a list
        // of «other ways to say the same thing» that quietly mixed languages would be worse than no
        // list at all.
        $allTranslations = $this->alternatives($ids, $translations);

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

        // Near-synonyms on the studied side. They ride with the term for the same reason the
        // variants above do — the device grades typed answers offline and must know the same
        // accepted set the server does — and they are kept as their OWN list because they are
        // accepted on fewer cards than a variant is (see TermContentView).
        $synonyms = [];
        foreach (DB::table('term_synonyms')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text']) as $row) {
            $synonyms[(string) $row->term_id][] = (string) $row->text;
        }

        // The description is written in the language BEING LEARNED — it is the question of the
        // description_match card, not a gloss for the learner's own language, so it is picked by
        // the TERM's language and not by the support language.
        $descriptions = [];
        foreach (DB::table('term_descriptions')->whereIn('term_id', $ids)->get(['term_id', 'lang', 'text']) as $row) {
            $descriptions[(string) $row->term_id][(string) $row->lang] = (string) $row->text;
        }

        // Distractors hang off the PINNED example's id, so only that example's rows are shipped.
        $exampleIds = [];
        foreach ($examples as $termId => $row) {
            $exampleIds[(string) $row->id] = (string) $termId;
        }

        // The gloss under the example is picked by the SAME support language as the term's own
        // translation — see ExampleTranslationPick. It used to be a single column with no language
        // at all, so a term glossed for a Ukrainian collection printed that gloss to a Russian
        // speaker with nothing to say it had happened. Grouped by language for the same reason the
        // translations above are, and by the language of the example's OWN term.
        /** @var array<string, list<string>> $exampleIdsByLang */
        $exampleIdsByLang = [];
        foreach ($exampleIds as $exampleId => $termId) {
            $exampleIdsByLang[$langs->for($termId)][] = (string) $exampleId;
        }
        $exampleTranslations = [];
        foreach ($exampleIdsByLang as $lang => $groupIds) {
            $exampleTranslations += $this->examplePick->textsFor($groupIds, $lang);
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
                exampleTranslation: $example !== null ? ($exampleTranslations[(string) $example->id] ?? null) : null,
                description: $descriptions[$id][(string) $term->lang] ?? null,
                imageUrl: $term->image_url !== null ? (string) $term->image_url : null,
                imageAuthor: $term->image_author !== null ? (string) $term->image_author : null,
                imageAuthorUrl: $term->image_author_url !== null ? (string) $term->image_author_url : null,
                acceptedVariants: $variants[$id] ?? [],
                exampleDistractors: $distractors[$id] ?? [],
                synonyms: $synonyms[$id] ?? [],
                // Every reading this term has in the asking language, the pinned one first. The
                // single `translation` above is unchanged and is still what the card asks — this is
                // the alternatives beside it, so a learner who types «задача» for `purpose` is not
                // told they are wrong by a card that simply pinned «цель».
                translations: $allTranslations[$id] ?? [],
            );
        }

        return $out;
    }

    /**
     * Every translation a term has in the language its pinned one is in, that pinned one first.
     *
     * Grouped by the RESOLVED language rather than by the asked-for one, which is the whole subtlety:
     * {@see TranslationPick} may fall back to a foreign-language row when a term has nothing in the
     * asking language, and the alternatives of such a term are the alternatives of the row that
     * actually won — not an empty list, and not a mixture.
     *
     * @param  list<string>  $ids
     * @param  array<string, array{id: string, lang: string, text: string}>  $picked
     * @return array<string, list<string>>
     */
    private function alternatives(array $ids, array $picked): array
    {
        /** @var array<string, list<string>> $byLang */
        $byLang = [];
        foreach ($ids as $id) {
            $lang = $picked[$id]['lang'] ?? null;
            if ($lang !== null) {
                $byLang[$lang][] = $id;
            }
        }

        $out = [];
        foreach ($byLang as $lang => $groupIds) {
            foreach (DB::table('term_translations')->whereIn('term_id', $groupIds)->where('lang', $lang)
                ->orderByDesc('is_primary')->orderBy('id')->get(['term_id', 'text']) as $row) {
                $out[(string) $row->term_id][] = (string) $row->text;
            }
        }

        return $out;
    }
}
