<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermExampleWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;
use Illuminate\Support\Facades\DB;

final class EloquentTermExampleWriter implements TermExampleWriter
{
    public function replace(
        TermId $termId,
        string $sentence,
        ?string $sentenceTranslation,
        array $dropDistractorSentences = [],
        string $source = 'user',
        ?Provenance $provenance = null,
    ): void {
        DB::transaction(function () use ($termId, $sentence, $sentenceTranslation, $dropDistractorSentences, $source, $provenance): void {
            $now = now();
            // Written on both paths below: the stamp belongs to the SENTENCE, and a sentence written
            // into an empty term is no less generated than one written over an old one.
            $stamp = [
                'source' => $source,
                'prompt_version' => $provenance?->promptVersion,
                'generation_model' => $provenance?->model,
            ];

            // The PINNED example — lowest id, the rule every reader in this module uses. It is the
            // only one the card shows and the only one distractors hang off.
            $pinned = DB::table('term_examples')
                ->where('term_id', $termId->value)
                ->orderBy('id')
                ->first(['id']);

            if ($pinned === null) {
                DB::table('term_examples')->insert([
                    'id' => Ulid::generate(),
                    'term_id' => $termId->value,
                    // The sentence USES the term, so it is written in the term's language. Read off
                    // the term rather than taken from the caller: a parameter would be a second
                    // opinion about a fact the row next door already holds.
                    'lang' => DB::table('terms')->where('id', $termId->value)->value('lang'),
                    'sentence' => $sentence,
                    'sentence_translation' => $sentenceTranslation,
                    ...$stamp,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $exampleId = (string) $pinned->id;

                // Orphaned distractors go first, inside the same transaction: a crash between the
                // two writes must not leave rows describing a sentence that is already gone.
                if ($dropDistractorSentences !== []) {
                    DB::table('example_distractors')
                        ->where('example_id', $exampleId)
                        ->whereIn('sentence', $dropDistractorSentences)
                        ->delete();
                }

                // In place, same id — see TermExampleWriter::replace(). Everything hanging off this
                // example that the caller did NOT name survives the replacement.
                DB::table('term_examples')->where('id', $exampleId)->update([
                    'sentence' => $sentence,
                    'sentence_translation' => $sentenceTranslation,
                    ...$stamp,
                    'updated_at' => $now,
                ]);

                // "Replace" has always meant the term ends up with ONE example. Any non-pinned rows
                // are still dropped; nothing reads them, and their distractors were never shipped.
                DB::table('term_examples')
                    ->where('term_id', $termId->value)
                    ->where('id', '!=', $exampleId)
                    ->delete();
            }

            // The delta feed decides what to ship by `terms.updated_at`, and the example lives in
            // ANOTHER table — so without this touch a replaced example stayed on the server and the
            // phone kept showing the old sentence forever (QA-19). Which is the worse half of the
            // bug: both callers exist to FIX a bad example — «Новый пример» by hand and the echo
            // repair automatically — and the fix reached everything except the device that had the
            // complaint. Unconditional, unlike the enrichment writer's version of this line: a
            // replace always writes a different sentence, so there is no no-op run to guard against.
            DB::table('terms')->where('id', $termId->value)->update(['updated_at' => $now]);
        });
    }
}
