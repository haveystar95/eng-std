<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermEnrichmentExportRow;
use App\Modules\Vocabulary\Application\Query\TermEnrichmentExportReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermEnrichmentExportReader implements TermEnrichmentExportReader
{
    public function __construct(private readonly TranslationPick $pick = new TranslationPick()) {}

    public function byIds(array $termIds, string $lang): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // Pinned example — `orderBy('id')`, as everywhere. Distractors hang off its id, so the
        // export must show the same sentence they were written against.
        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')->get(['id', 'term_id', 'sentence']) as $row) {
            $examples[(string) $row->term_id] ??= $row;
        }

        // Same rule as the card the proofreader is judging — an export that showed a different
        // translation than the app does would have a human correcting a row nobody sees.
        $translations = $this->pick->forTerms($ids, $lang);

        $variants = [];
        foreach (DB::table('term_accepted_variants')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text', 'note']) as $row) {
            $variants[(string) $row->term_id][] = [
                'text' => (string) $row->text,
                'note' => $row->note !== null ? (string) $row->note : null,
            ];
        }

        $exampleIds = array_map(static fn (object $e): string => (string) $e->id, $examples);
        $distractors = [];
        if ($exampleIds !== []) {
            foreach (DB::table('example_distractors')->whereIn('example_id', array_values($exampleIds))->orderBy('id')
                ->get(['example_id', 'sentence', 'error_type', 'error_span', 'correction']) as $row) {
                $distractors[(string) $row->example_id][] = [
                    'sentence' => (string) $row->sentence,
                    'error_type' => (string) $row->error_type,
                    'error_span' => (string) $row->error_span,
                    'correction' => (string) $row->correction,
                ];
            }
        }

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get(['id', 'text']) as $term) {
            $id = (string) $term->id;
            $example = $examples[$id] ?? null;

            $out[$id] = new TermEnrichmentExportRow(
                termId: $id,
                text: (string) $term->text,
                translation: $translations[$id]['text'] ?? null,
                exampleSentence: $example !== null && $example->sentence !== null ? (string) $example->sentence : null,
                variants: $variants[$id] ?? [],
                distractors: $example !== null ? ($distractors[(string) $example->id] ?? []) : [],
            );
        }

        return $out;
    }
}
