<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use Illuminate\Support\Facades\DB;

/**
 * Which of an example's translations is THE one, for a given asking language.
 *
 * The sibling of {@see TranslationPick}, and deliberately the same rule: an example is a sentence
 * with a question beside it, and once the translation carries a language of its own, "which row"
 * becomes exactly the question `term_translations` has always had to answer.
 *
 *   1. the asked-for language;
 *   2. an EXPLICIT fallback — any other language — because a card whose example gloss is in the
 *      wrong language still beats a card with no gloss at all, and every row backfilled out of the
 *      old column carries whichever language that collection supported, not necessarily this
 *      learner's;
 *   3. `id` ascending inside every tier, so "the row we showed yesterday" is the row we show today.
 *
 * There is no `is_primary` tier: an example has at most one translation PER LANGUAGE (the unique
 * index says so), so within a language there is nothing to choose between.
 *
 * The fallback is what keeps this change invisible on the wire. Before it, the reader took the one
 * translation the row happened to hold, whatever language it was in; a language-filtered read with
 * no fallback would have blanked the gloss on every term whose translation is not in the asking
 * language — a regression dressed up as correctness. Choosing the RIGHT language when several exist
 * is the new behaviour; never choosing NOTHING is the old one, kept.
 */
final class ExampleTranslationPick
{
    /**
     * The winning translation per example.
     *
     * @param  list<string>  $exampleIds
     * @return array<string, array{lang: string, text: string}>  keyed by example id
     */
    public function forExamples(array $exampleIds, string $lang): array
    {
        if ($exampleIds === []) {
            return [];
        }

        $picked = [];
        foreach (DB::table('example_translations')
            ->whereIn('term_example_id', $exampleIds)
            // `CASE WHEN … THEN 0 ELSE 1 END` rather than `(lang = ?) DESC`, for the reason
            // TranslationPick gives: the rule means the same thing on any engine.
            ->orderByRaw('CASE WHEN lang = ? THEN 0 ELSE 1 END', [$lang])
            ->orderBy('id')
            ->get(['term_example_id', 'lang', 'text']) as $row) {
            // First row per example wins — and "first" is a total order, not an accident.
            $picked[(string) $row->term_example_id] ??= [
                'lang' => (string) $row->lang,
                'text' => (string) $row->text,
            ];
        }

        return $picked;
    }

    /**
     * The gloss IN THIS LANGUAGE AND NO OTHER — the search reader's variant, for the reason
     * {@see TranslationPick::forTermsInLanguage()} gives: a hit answers the pair it was asked in,
     * and a gloss in a third language under an example is not a smaller version of the answer, it
     * is a different one (DECISIONS п. 146).
     *
     * @param  list<string>  $exampleIds
     * @return array<string, string>  keyed by example id
     */
    public function textsInLanguage(array $exampleIds, string $lang): array
    {
        if ($exampleIds === []) {
            return [];
        }

        $picked = [];
        foreach (DB::table('example_translations')
            ->whereIn('term_example_id', $exampleIds)
            ->where('lang', $lang)
            ->orderBy('id')
            ->get(['term_example_id', 'text']) as $row) {
            $picked[(string) $row->term_example_id] ??= (string) $row->text;
        }

        return $picked;
    }

    /**
     * The text alone, for the readers that only print it.
     *
     * @param  list<string>  $exampleIds
     * @return array<string, string>  keyed by example id
     */
    public function textsFor(array $exampleIds, string $lang): array
    {
        return array_map(
            static fn (array $row): string => $row['text'],
            $this->forExamples($exampleIds, $lang),
        );
    }
}
