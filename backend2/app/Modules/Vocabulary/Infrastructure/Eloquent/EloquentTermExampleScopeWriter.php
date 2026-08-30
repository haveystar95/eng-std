<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermExampleScopeWriter;
use Illuminate\Support\Facades\DB;

/**
 * A day-scoped example row, plus its gloss in `example_translations`.
 *
 * NOT {@see \App\Modules\Vocabulary\Application\Port\TermExampleWriter}, and the difference is the
 * word «pinned». That port REPLACES the term's one shown example in place, keeping its id so the
 * distractors hanging off it survive — which is exactly right for a term that has one example and
 * exactly wrong here: a plan day ADDS a sentence that belongs to that day, beside whatever the term
 * already had. Replacing would mean day 3 of a plan quietly overwriting the general example every
 * other learner sees.
 *
 * Idempotent on (term, sentence, scope) rather than on (term, sentence): the same sentence may
 * legitimately exist both as a general example and as this day's, and a re-run of one day must
 * neither duplicate its own rows nor touch the general one.
 */
final readonly class EloquentTermExampleScopeWriter implements TermExampleScopeWriter
{
    public function write(
        TermId $termId,
        string $sentence,
        ?string $sentenceTranslation,
        string $translationLang,
        CollectionId $scope,
    ): void {
        $sentence = trim($sentence);
        if ($sentence === '') {
            return;
        }

        $existing = DB::table('term_examples')
            ->where('term_id', $termId->value)
            ->where('sentence', $sentence)
            ->where('scope_collection_id', $scope->value)
            ->value('id');

        if (is_string($existing)) {
            return;
        }

        // The sentence's own language is the TERM's — an example is a sentence using the term, so
        // it is not a judgement call and is read from the row rather than passed in.
        $lang = DB::table('terms')->where('id', $termId->value)->value('lang');
        if (! is_string($lang)) {
            return;
        }

        $exampleId = Ulid::generate();
        $now = now();

        DB::table('term_examples')->insert([
            'id' => $exampleId,
            'term_id' => $termId->value,
            'lang' => $lang,
            'sentence' => $sentence,
            // The deprecated column is filled alongside the new table for as long as it exists —
            // phase A of the multilanguage move is not finished, and a writer that skipped it
            // would make plan examples the only ones invisible to the readers still on it.
            'sentence_translation' => $sentenceTranslation,
            'source' => 'ai',
            'scope_collection_id' => $scope->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $translation = trim((string) $sentenceTranslation);
        if ($translation !== '') {
            DB::table('example_translations')->insert([
                'id' => Ulid::generate(),
                'term_example_id' => $exampleId,
                'lang' => $translationLang,
                'text' => $translation,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
