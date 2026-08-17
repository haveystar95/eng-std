<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\TermLanguageRow;
use App\Modules\Vocabulary\Application\Query\TermLanguageAuditReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermLanguageAuditReader implements TermLanguageAuditReader
{
    public function reachableRows(string $sourceLang): array
    {
        // Reachable = in a live collection built for this learner language. `collection_items` is
        // soft-deleted, and a term that has left every deck is as unreachable as one that never
        // joined one.
        $termIds = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('c.source_lang', $sourceLang)
            ->distinct()
            ->pluck('ci.term_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return $this->rowsForTerms(array_values($termIds), $sourceLang);
    }

    public function orphanRows(string $sourceLang): array
    {
        // "In no collection" is checked without a language filter — a term reachable only through a
        // German-target collection is still reachable, and not the orphan this scope means.
        $memberTermIds = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->whereNull('ci.deleted_at')
            ->whereNull('c.deleted_at')
            ->distinct()
            ->pluck('ci.term_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $termIds = DB::table('terms')
            ->whereNull('deleted_at')
            ->when($memberTermIds !== [], static fn ($q) => $q->whereNotIn('id', $memberTermIds))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return $this->rowsForTerms(array_values($termIds), $sourceLang);
    }

    /**
     * @param  list<string>  $termIds
     * @return list<TermLanguageRow>
     */
    private function rowsForTerms(array $termIds, string $sourceLang): array
    {
        if ($termIds === []) {
            return [];
        }

        $terms = DB::table('terms')
            ->whereIn('id', $termIds)
            ->whereNull('deleted_at')
            ->get(['id', 'text', 'type', 'lang'])
            ->keyBy(fn (object $t): string => (string) $t->id);

        // Which terms already hold a translation in the expected language. It is the difference
        // between "delete this row" and "there is nothing left to show, translate it again".
        $hasSibling = [];
        foreach (DB::table('term_translations')->whereIn('term_id', $termIds)->where('lang', $sourceLang)
            ->distinct()->pluck('term_id') as $id) {
            $hasSibling[(string) $id] = true;
        }

        $rows = [];

        foreach (DB::table('term_translations')->whereIn('term_id', $termIds)->orderBy('id')
            ->get(['id', 'term_id', 'lang', 'text']) as $row) {
            $termId = (string) $row->term_id;
            $term = $terms[$termId] ?? null;
            if ($term === null) {
                continue;
            }
            $rows[] = new TermLanguageRow(
                termId: $termId,
                termText: (string) $term->text,
                termType: (string) $term->type,
                termLang: (string) $term->lang,
                field: 'translation',
                rowId: (string) $row->id,
                declaredLang: (string) $row->lang,
                value: (string) $row->text,
                exampleSentence: null,
                // A row in the expected language is its own sibling; what matters to the caller is
                // whether removing THIS row would leave the term без перевода.
                hasSiblingInDeclared: isset($hasSibling[$termId]) && (string) $row->lang !== $sourceLang,
            );
        }

        foreach (DB::table('term_examples')->whereIn('term_id', $termIds)->whereNotNull('sentence_translation')
            ->orderBy('id')->get(['id', 'term_id', 'sentence', 'sentence_translation']) as $row) {
            $termId = (string) $row->term_id;
            $term = $terms[$termId] ?? null;
            if ($term === null) {
                continue;
            }
            $rows[] = new TermLanguageRow(
                termId: $termId,
                termText: (string) $term->text,
                termType: (string) $term->type,
                termLang: (string) $term->lang,
                field: 'example_translation',
                rowId: (string) $row->id,
                // The table has no language column: the field is the learner's language by contract.
                declaredLang: $sourceLang,
                value: (string) $row->sentence_translation,
                exampleSentence: (string) $row->sentence,
                hasSiblingInDeclared: false,
            );
        }

        return $rows;
    }
}
