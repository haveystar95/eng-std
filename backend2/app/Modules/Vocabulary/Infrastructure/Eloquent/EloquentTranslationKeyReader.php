<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

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
