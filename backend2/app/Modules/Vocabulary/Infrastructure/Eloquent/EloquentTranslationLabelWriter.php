<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\RepairedTranslationRow;
use App\Modules\Vocabulary\Application\Port\TranslationLabelWriter;
use Illuminate\Support\Facades\DB;

final class EloquentTranslationLabelWriter implements TranslationLabelWriter
{
    public function rowsNotLabelled(string $lang): array
    {
        $out = [];
        foreach (DB::table('term_translations as tr')
            ->join('terms as t', 't.id', '=', 'tr.term_id')
            ->where('tr.lang', '<>', $lang)
            ->orderBy('tr.id')
            ->get(['tr.id', 'tr.term_id', 'tr.lang', 'tr.text', 'tr.created_at', 'tr.updated_at', 't.text as term_text']) as $row) {
            $out[] = new RepairedTranslationRow(
                rowId: (string) $row->id,
                termId: (string) $row->term_id,
                termText: (string) $row->term_text,
                declaredLang: (string) $row->lang,
                text: (string) $row->text,
                // Null updated_at (never touched) counts as "not rewritten".
                rewrittenSinceCreation: $row->updated_at !== null
                    && $row->created_at !== null
                    && $row->updated_at > $row->created_at,
            );
        }

        return $out;
    }

    public function relabel(array $rowIds, string $lang): int
    {
        if ($rowIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($rowIds, $lang): int {
            $termIds = DB::table('term_translations')->whereIn('id', $rowIds)->distinct()->pluck('term_id')->all();

            $changed = DB::table('term_translations')
                ->whereIn('id', $rowIds)
                ->update(['lang' => $lang, 'updated_at' => now()]);

            // The delta sync watches terms.updated_at and nothing else.
            if ($termIds !== []) {
                DB::table('terms')->whereIn('id', $termIds)->update(['updated_at' => now()]);
            }

            return $changed;
        });
    }
}
