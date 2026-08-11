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
            $written = 0;

            if ($variants !== []) {
                // insertOrIgnore against `term_accepted_variants_uidx`: a variant this term already
                // accepts keeps its existing row (and its hand-edited note), so a re-run neither
                // duplicates nor overwrites. Same reason the distractor insert below is ignoring.
                $written += DB::table('term_accepted_variants')->insertOrIgnore(array_map(
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
                $written += DB::table('example_distractors')->insertOrIgnore(array_map(
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

            // The delta feed decides what to ship by `terms.updated_at`, and these rows live in
            // OTHER tables — so without this touch the станок's output would sit on the server
            // invisible to every already-synced device, and offline grading would keep rejecting
            // answers the server accepts. Touched only when something was actually inserted, so a
            // no-op re-run doesn't re-send the whole collection to every client.
            if ($written > 0) {
                DB::table('terms')->where('id', $termId->value)->update(['updated_at' => $now]);
            }
        });
    }
}
