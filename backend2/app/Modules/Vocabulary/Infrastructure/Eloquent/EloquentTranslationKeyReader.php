<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\ExampleKeyRow;
use App\Modules\Vocabulary\Application\Dto\TranslationKeyRow;
use App\Modules\Vocabulary\Application\Query\TranslationKeyReader;
use Illuminate\Support\Facades\DB;

final class EloquentTranslationKeyReader implements TranslationKeyReader
{
    public function primaryKeys(string $termLang, string $translationLang): array
    {
        // Vocabulary's own two tables and no others. Where a term is USED is Collections' fact, and
        // a join to `collection_items` from here would be a second place that knows what a live deck
        // is — see Collections\Application\Query\TermDeckTitleReader, which the caller composes with.
        $rows = DB::table('terms as t')
            ->join('term_translations as tr', 'tr.term_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $termLang)
            ->where('tr.lang', $translationLang)
            // The PRIMARY translation only: the others are accepted answers, never the question.
            ->where('tr.is_primary', true)
            ->orderBy('t.text')
            ->get(['t.id as term_id', 't.text as term_text', 'tr.id as translation_id', 'tr.text as translation']);

        return array_values($rows->map(static fn (object $r): TranslationKeyRow => new TranslationKeyRow(
            termId: (string) $r->term_id,
            termText: (string) $r->term_text,
            translationId: (string) $r->translation_id,
            translation: (string) $r->translation,
        ))->all());
    }

    public function primaryExampleKeys(string $termLang, string $translationLang): array
    {
        $rows = DB::table('terms as t')
            ->join('term_examples as e', 'e.term_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $termLang)
            ->whereNotNull('e.sentence_translation')
            ->where('e.sentence_translation', '<>', '')
            // The example's own language is not recorded, so it is taken from the term's primary
            // translation — and only when that is unambiguous. See the port for why a guess is worse
            // than a skip.
            ->whereExists(fn ($q) => $q->from('term_translations as tr')
                ->whereColumn('tr.term_id', 't.id')
                ->where('tr.is_primary', true)
                ->where('tr.lang', $translationLang))
            ->whereRaw(
                '(select count(distinct tr2.lang) from term_translations tr2 where tr2.term_id = t.id and tr2.is_primary) = 1',
            )
            ->orderBy('t.text')
            ->orderBy('e.id')
            ->get(['t.id as term_id', 't.text as term_text', 'e.id as example_id', 'e.sentence', 'e.sentence_translation']);

        return array_values($rows->map(static fn (object $r): ExampleKeyRow => new ExampleKeyRow(
            termId: (string) $r->term_id,
            termText: (string) $r->term_text,
            exampleId: (string) $r->example_id,
            sentence: (string) $r->sentence,
            translation: (string) $r->sentence_translation,
        ))->all());
    }

    public function examplesOfUnknownLangCount(string $termLang): int
    {
        return DB::table('terms as t')
            ->join('term_examples as e', 'e.term_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $termLang)
            ->whereNotNull('e.sentence_translation')
            ->where('e.sentence_translation', '<>', '')
            ->whereRaw(
                '(select count(distinct tr.lang) from term_translations tr where tr.term_id = t.id and tr.is_primary) > 1',
            )
            ->count();
    }

    public function translationLangs(string $termLang): array
    {
        // The same WHERE as above, minus the language: whatever the sweep will judge is whatever
        // this returns, so a row can never be counted by one and skipped by the other.
        $langs = DB::table('terms as t')
            ->join('term_translations as tr', 'tr.term_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $termLang)
            ->where('tr.is_primary', true)
            ->distinct()
            ->orderBy('tr.lang')
            ->pluck('tr.lang');

        return array_values($langs->map(static fn (mixed $l): string => (string) $l)->all());
    }
}
