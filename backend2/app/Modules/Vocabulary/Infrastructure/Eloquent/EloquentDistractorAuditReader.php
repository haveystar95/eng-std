<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\DistractorAuditRow;
use App\Modules\Vocabulary\Application\Query\DistractorAuditReader;
use Illuminate\Support\Facades\DB;

final class EloquentDistractorAuditReader implements DistractorAuditReader
{
    public function all(): array
    {
        // The PINNED example only — `orderBy('id')`, the rule every other reader uses. A distractor
        // hanging off a non-pinned example is never shown, so auditing it would edit invisible content.
        $pinned = [];
        foreach (DB::table('term_examples')->orderBy('id')->get(['id', 'term_id']) as $row) {
            $pinned[(string) $row->term_id] ??= (string) $row->id;
        }

        return $this->rows(array_values($pinned));
    }

    public function forTerm(string $termId): array
    {
        $pinned = DB::table('term_examples')
            ->where('term_id', $termId)
            ->orderBy('id')
            ->first(['id']);

        return $pinned === null ? [] : $this->rows([(string) $pinned->id]);
    }

    /**
     * @param  list<string>  $exampleIds
     * @return list<DistractorAuditRow>
     */
    private function rows(array $exampleIds): array
    {
        if ($exampleIds === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('example_distractors as d')
            ->join('term_examples as e', 'e.id', '=', 'd.example_id')
            ->join('terms as t', 't.id', '=', 'e.term_id')
            ->whereIn('d.example_id', $exampleIds)
            ->orderBy('d.example_id')
            ->orderBy('d.id')
            ->get([
                't.id as term_id', 't.text as term_text', 'e.id as example_id', 'e.sentence as example_sentence',
                'd.sentence', 'd.error_type', 'd.error_span', 'd.correction', 'd.generator_version',
            ]) as $row) {
            $out[] = new DistractorAuditRow(
                termId: (string) $row->term_id,
                termText: (string) $row->term_text,
                exampleId: (string) $row->example_id,
                exampleSentence: (string) $row->example_sentence,
                sentence: (string) $row->sentence,
                errorType: (string) $row->error_type,
                errorSpan: (string) $row->error_span,
                correction: (string) $row->correction,
                generatorVersion: (string) $row->generator_version,
            );
        }

        return $out;
    }
}
