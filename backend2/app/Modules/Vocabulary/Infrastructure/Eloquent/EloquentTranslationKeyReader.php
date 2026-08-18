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
        $rows = DB::table('terms as t')
            ->join('term_translations as tr', 'tr.term_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $termLang)
            ->where('tr.lang', $translationLang)
            // The PRIMARY translation only: the others are accepted answers, never the question.
            ->where('tr.is_primary', true)
            ->orderBy('t.text')
            ->get(['t.id as term_id', 't.text as term_text', 'tr.id as translation_id', 'tr.text as translation']);

        $decks = $this->collectionTitlesByTerm(
            array_values($rows->map(static fn (object $r): string => (string) $r->term_id)->all()),
        );

        return array_values($rows->map(fn (object $r): TranslationKeyRow => new TranslationKeyRow(
            termId: (string) $r->term_id,
            termText: (string) $r->term_text,
            translationId: (string) $r->translation_id,
            translation: (string) $r->translation,
            collections: $decks[(string) $r->term_id] ?? [],
        ))->all());
    }

    /**
     * Live decks per term. Soft-deleted items and collections are left out: a term that has left
     * every deck is not a question anyone is being asked, and saying otherwise would send the owner
     * proof-reading content nobody sees.
     *
     * @param  list<string>  $termIds
     * @return array<string, list<string>>
     */
    private function collectionTitlesByTerm(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->whereIn('ci.term_id', $termIds)
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->orderBy('c.title')
            ->get(['ci.term_id', 'c.title'])
            ->each(function (object $r) use (&$out): void {
                $out[(string) $r->term_id][] = (string) $r->title;
            });

        return array_map(static fn (array $titles): array => array_values(array_unique($titles)), $out);
    }
}
