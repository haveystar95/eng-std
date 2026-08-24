<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which of a term's translations is THE one, for a given asking language.
 *
 * A term is global and deduplicated, so it accumulates translation rows: every regeneration of the
 * same text merges another one in ({@see \App\Modules\Vocabulary\Application\Command\FindOrCreateTermHandler}),
 * and rows in different languages sit side by side legitimately — a Ukrainian translation next to a
 * Russian one is not corrupt data, it is simply not the row a Russian-speaking learner should be
 * asked. Choosing between them was, until this class existed, four copies of
 * `orderByDesc('is_primary')` with no language filter and no second key: the winner among equals was
 * whatever the heap handed back. Live proof, from docs/generation-acceptance.md: the same term
 * answered «Могу я оплатить картой?» through the API and «Могу ли я заплатить картой?» through psql,
 * in the same minute. The translation is the QUESTION on the card, so that is a coin flip over what
 * the learner is asked.
 *
 * One class, four callers, one rule:
 *
 *   1. the asked-for language, marked primary;
 *   2. the asked-for language, any row;
 *   3. an EXPLICIT fallback — any other language — because a card whose question is in the wrong
 *      language still beats a card with no question at all, and 103 live terms had nothing else to
 *      offer until their rows were relabelled;
 *   4. `id` ascending inside every tier, so "the row we showed yesterday" is the row we show today.
 *
 * The fallback is a deliberate last resort and not a silent one: it only fires for a term with no
 * row in the asked-for language at all. SEARCH does not want even that — it asks
 * {@see forTermsInLanguage()} instead, because there the alternative to a wrong-language gloss is a
 * paid lookup that answers in the right one (DECISIONS п. 146).
 */
final class TranslationPick
{
    /**
     * The winning translation per term.
     *
     * @param  list<string>  $termIds
     * @return array<string, array{id: string, lang: string, text: string}>  keyed by term id
     */
    public function forTerms(array $termIds, string $lang): array
    {
        if ($termIds === []) {
            return [];
        }

        $picked = [];
        foreach (self::ordered(DB::table('term_translations')->whereIn('term_id', $termIds), $lang)
            ->get(['id', 'term_id', 'lang', 'text']) as $row) {
            // First row per term wins — and now "first" is a total order, not an accident.
            $picked[(string) $row->term_id] ??= [
                'id' => (string) $row->id,
                'lang' => (string) $row->lang,
                'text' => (string) $row->text,
            ];
        }

        return $picked;
    }

    /**
     * The winning translation per term, IN THIS LANGUAGE AND NO OTHER.
     *
     * The same rule as {@see forTerms()} minus its third tier: a term with no row in `$lang` gets
     * no answer at all rather than a row in somebody else's language. Two readers want that and
     * they are both SEARCH (DECISIONS п. 146): a hit answers the pair the learner asked in, and if
     * we have nothing in that pair the live lookup is one tap away and will write it. The fallback
     * stays where it belongs — on the card and in the trainer, where the alternative to a
     * wrong-language gloss is a card with no question on it.
     *
     * Live proof that this is not tidiness: `invoice` looked up in RU → EN came back with the
     * ROMANIAN `factură`, because the term had picked up a Romanian translation on a different
     * pair and the fallback served it as if it were the answer.
     *
     * @param  list<string>  $termIds
     * @return array<string, array{id: string, lang: string, text: string}>  keyed by term id
     */
    public function forTermsInLanguage(array $termIds, string $lang): array
    {
        if ($termIds === []) {
            return [];
        }

        $picked = [];
        foreach (DB::table('term_translations')
            ->whereIn('term_id', $termIds)
            ->where('lang', $lang)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['id', 'term_id', 'lang', 'text']) as $row) {
            $picked[(string) $row->term_id] ??= [
                'id' => (string) $row->id,
                'lang' => (string) $row->lang,
                'text' => (string) $row->text,
            ];
        }

        return $picked;
    }

    /**
     * The ordering itself, exposed so a writer that has to find the SAME row a reader would show
     * (the curator's primary-translation update) sorts by the identical rule rather than a lookalike.
     *
     * `CASE WHEN … THEN 0 ELSE 1 END` rather than `(lang = ?) DESC`: both work on Postgres, and the
     * former also means the same thing on any other engine, which matters for a rule whose whole
     * value is being identical everywhere.
     */
    public static function ordered(Builder $query, string $lang): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN lang = ? THEN 0 ELSE 1 END', [$lang])
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }
}
