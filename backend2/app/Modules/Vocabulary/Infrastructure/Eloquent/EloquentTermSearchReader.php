<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\TermSearchRow;
use App\Modules\Vocabulary\Application\Query\TermSearchReader;
use Illuminate\Support\Facades\DB;

/**
 * Exact and prefix matching over `terms.normalized_text` and over the learner-language rows of
 * `term_translations`, in one query per side.
 *
 * Deliberately NOT full-text or trigram search. The question this answers is «do we already have
 * the word you just typed» — a question with a right answer, where a fuzzy match that returns
 * something plausible instead of nothing is actively harmful: the learner takes the near-miss and
 * never generates the word they actually meant. Fuzziness belongs one layer up, where the answer
 * «we don't have it, want to generate it?» is available.
 *
 * The rank column is the ORDER, computed in SQL so the limit applies to the ranked set rather than
 * to whatever the index handed back first: 0 = exact term, 1 = exact translation, 2 = prefix term,
 * 3 = prefix translation.
 */
final class EloquentTermSearchReader implements TermSearchReader
{
    /** Below this, a prefix search matches most of the dictionary and ranks by accident. */
    private const MIN_PREFIX_LENGTH = 2;

    public function search(string $query, string $lang, string $nativeLang, int $limit = 20): array
    {
        $needle = $this->normalize($query);
        if ($needle === '') {
            return [];
        }

        $ranked = $this->rankedIds($needle, $lang, $nativeLang, $limit);
        if ($ranked === []) {
            return [];
        }

        return $this->hydrate($ranked, $nativeLang);
    }

    /**
     * @return array<string, array{rank: int, matched_term: bool}>  term id => how it matched
     */
    private function rankedIds(string $needle, string $lang, string $nativeLang, int $limit): array
    {
        $prefix = mb_strlen($needle) >= self::MIN_PREFIX_LENGTH
            ? $this->escapeLike($needle) . '%'
            : null;

        $terms = DB::table('terms')
            ->whereNull('deleted_at')
            ->where('lang', $lang)
            ->where(function ($q) use ($needle, $prefix): void {
                $q->where('normalized_text', $needle);
                if ($prefix !== null) {
                    $q->orWhere('normalized_text', 'like', $prefix);
                }
            })
            ->selectRaw('id, CASE WHEN normalized_text = ? THEN 0 ELSE 2 END as rank', [$needle])
            ->orderBy('rank')->orderBy('normalized_text')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($terms as $row) {
            $out[(string) $row->id] = ['rank' => (int) $row->rank, 'matched_term' => true];
        }

        // The translation side runs even when the term side filled the page: a learner typing
        // «счёт» gets nothing from the term side at all, and one typing «bank» should still see the
        // exact term above any translation hit — which the rank ordering below guarantees.
        $translations = DB::table('term_translations as tt')
            ->join('terms as t', 't.id', '=', 'tt.term_id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $lang)
            ->where('tt.lang', $nativeLang)
            ->where(function ($q) use ($needle, $prefix): void {
                $q->whereRaw('lower(tt.text) = ?', [$needle]);
                if ($prefix !== null) {
                    $q->orWhereRaw('lower(tt.text) like ?', [$prefix]);
                }
            })
            ->selectRaw('tt.term_id, CASE WHEN lower(tt.text) = ? THEN 1 ELSE 3 END as rank', [$needle])
            ->orderBy('rank')
            ->limit($limit)
            ->get();

        foreach ($translations as $row) {
            $id = (string) $row->term_id;
            // A term matched on BOTH sides keeps its (better) term-side rank.
            $out[$id] ??= ['rank' => (int) $row->rank, 'matched_term' => false];
        }

        uasort($out, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        return array_slice($out, 0, $limit, preserve_keys: true);
    }

    /**
     * @param  array<string, array{rank: int, matched_term: bool}>  $ranked
     * @return list<TermSearchRow>
     */
    private function hydrate(array $ranked, string $nativeLang): array
    {
        $ids = array_keys($ranked);

        // The same deterministic pick the card builder uses, so a word found in search reads exactly
        // as it will read on its card.
        $translations = (new TranslationPick())->forTerms($ids, $nativeLang);

        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')->get() as $row) {
            $examples[(string) $row->term_id] ??= $row;
        }

        // Same rule as the card: the gloss is picked by the learner's language, with a fallback to
        // whatever gloss exists rather than none at all.
        $exampleTranslations = (new ExampleTranslationPick())->textsFor(
            array_values(array_map(static fn (object $e): string => (string) $e->id, $examples)),
            $nativeLang,
        );

        $descriptions = [];
        foreach (DB::table('term_descriptions')->whereIn('term_id', $ids)->get(['term_id', 'lang', 'text']) as $row) {
            $descriptions[(string) $row->term_id][(string) $row->lang] = (string) $row->text;
        }

        $rows = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get() as $term) {
            $id = (string) $term->id;
            $example = $examples[$id] ?? null;
            $rows[$id] = new TermSearchRow(
                id: $id,
                lang: (string) $term->lang,
                text: (string) $term->text,
                type: (string) $term->type,
                transcription: $term->ipa !== null ? (string) $term->ipa : null,
                translation: $translations[$id]['text'] ?? null,
                // The description is written in the language BEING LEARNED — that is what the
                // trainer shows and what the search card prints under the translation.
                description: $descriptions[$id][(string) $term->lang] ?? null,
                example: $example?->sentence !== null ? (string) $example->sentence : null,
                exampleTranslation: $example !== null ? ($exampleTranslations[(string) $example->id] ?? null) : null,
                cefr: $term->cefr !== null ? (string) $term->cefr : null,
                matchedTerm: $ranked[$id]['matched_term'],
            );
        }

        // Back into rank order: the hydration query returns rows in whatever order it likes.
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $ordered[] = $rows[$id];
            }
        }

        return $ordered;
    }

    /** Same casefold + whitespace collapse the dedup key uses, so an exact hit really is exact. */
    private function normalize(string $query): string
    {
        return (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($query)));
    }

    /** `%`, `_` and `\` are wildcards in LIKE; a learner typing one means the character. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
