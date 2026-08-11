<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermEnrichmentWriter;
use Illuminate\Support\Facades\DB;

final class EloquentTermEnrichmentWriter implements TermEnrichmentWriter
{
    public function append(
        TermId $termId,
        ?string $exampleId,
        array $variants,
        array $distractors,
        string $generatorVersion,
    ): void {
        if ($variants === [] && ($exampleId === null || $distractors === [])) {
            return;
        }

        DB::transaction(function () use ($termId, $exampleId, $variants, $distractors, $generatorVersion): void {
            $now = now();

            if ($variants !== []) {
                // insertOrIgnore against `term_accepted_variants_uidx`: a variant this term already
                // accepts keeps its existing row (and its hand-edited note), so a re-run neither
                // duplicates nor overwrites. Same reason the distractor insert below is ignoring.
                DB::table('term_accepted_variants')->insertOrIgnore(array_map(
                    static fn ($v): array => [
                        'id' => Ulid::generate(),
                        'term_id' => $termId->value,
                        'text' => $v->text,
                        'note' => $v->note,
                        'generator_version' => $generatorVersion,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $variants,
                ));
            }

            if ($exampleId !== null && $distractors !== []) {
                DB::table('example_distractors')->insertOrIgnore(array_map(
                    static fn ($d): array => [
                        'id' => Ulid::generate(),
                        'example_id' => $exampleId,
                        'sentence' => $d->sentence,
                        'error_type' => $d->errorType,
                        'error_span' => $d->errorSpan,
                        'correction' => $d->correction,
                        'generator_version' => $generatorVersion,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $distractors,
                ));
            }
        });
    }
}
