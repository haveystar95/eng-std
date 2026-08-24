<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The language of an example, said out loud instead of assumed (DECISIONS п. 138).
     *
     * `term_examples` recorded two strings and no language for either. The SENTENCE's language was
     * never in doubt — it is the term's, and the term has one — but `sentence_translation` was
     * whatever language the collection that first pulled the term in happened to support. A term
     * translated into ru AND uk therefore had exactly ONE example translation, belonging to
     * whichever of the two arrived first, and every reader had to guess: the audit reader called it
     * `$sourceLang` by contract, the key sweep inferred it from the term's primary translation and
     * SKIPPED the terms where that was ambiguous (`examplesOfUnknownLangCount()`), and the card
     * simply printed it beside a question in a language that may not have matched.
     *
     * So: `term_examples.lang` for the sentence (= the term's language, the studied side of the
     * pair), and a row-per-language `example_translations` for the support side — the same shape
     * `term_translations` has had since day one, and for the same reason.
     *
     * ## The backfill, and where it refuses to guess
     *
     * - `lang` comes from `terms.lang`. Not a heuristic: the example is a sentence USING the term,
     *   `term_id` is a non-null FK, and `terms.lang` is NOT NULL — every row is derivable and none
     *   is a judgement call.
     * - the translation's language is resolved THE WAY THE READING CODE RESOLVES IT TODAY: the
     *   term's primary translation (`is_primary DESC, id ASC`), which is what
     *   {@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentExampleRegenContextReader}
     *   and {@see \App\Modules\Vocabulary\Application\Query\TranslationKeyReader::primaryExampleKeys()}
     *   already use. Moving the data is not the moment to also change what it means.
     * - a term whose translations are PRIMARY IN MORE THAN ONE LANGUAGE has no answer, and one is
     *   not invented: the row keeps the (retained) old column and is logged by term id, so the
     *   ambiguity is a worklist and not a silent mislabel.
     * - an example whose term has NO translation row at all is logged the same way. There is no
     *   language to copy and no default worth pretending about.
     *
     * ## The old column stays
     *
     * `sentence_translation` is not dropped here. Phase A moves the pair-of-languages decision in
     * several steps and a column dropped mid-flight is a rollback nobody can perform; it is marked
     * DEPRECATED in the schema itself (a Postgres column comment, so `\d+` says it too) and goes in
     * its own micro-commit once the phase has fully landed. Same rule as the RENAME-DEFERRED marker
     * on `min_reps`: the honest name arrives with the deliberate move, not as a side effect.
     */
    public function up(): void
    {
        Schema::table('term_examples', function (Blueprint $table): void {
            $table->string('lang', 5)->nullable()->after('term_id');
        });

        // The sentence is written in the term's language, always. One statement, no chunking:
        // the join is on the primary key of a table this one already has a FK into.
        DB::statement('UPDATE term_examples e SET lang = t.lang FROM terms t WHERE t.id = e.term_id');

        // NOT NULL only after the backfill — an example with no language is exactly the state this
        // migration exists to end, so the schema is allowed to say it cannot happen again.
        DB::statement('ALTER TABLE term_examples ALTER COLUMN lang SET NOT NULL');

        Schema::create('example_translations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_example_id', 26);
            $table->string('lang', 5);
            $table->text('text');
            $table->timestampsTz();

            $table->foreign('term_example_id')->references('id')->on('term_examples')->cascadeOnDelete();
            // One translation per (example, language) — the card shows exactly one, so a heap of
            // rows to choose from would make "which translation" a question answered by accident.
            $table->unique(['term_example_id', 'lang'], 'example_translations_uidx');
        });

        $this->backfillTranslations();

        DB::statement(
            "COMMENT ON COLUMN term_examples.sentence_translation IS "
            . "'DEPRECATED — the example translation lives in example_translations, with its language. "
            . "Kept until phase A of the multilanguage move has fully landed; dropped in its own migration then.'",
        );
    }

    public function down(): void
    {
        DB::statement('COMMENT ON COLUMN term_examples.sentence_translation IS NULL');
        Schema::dropIfExists('example_translations');
        Schema::table('term_examples', function (Blueprint $table): void {
            $table->dropColumn('lang');
        });
    }

    /**
     * Copy every non-empty `sentence_translation` into a row that names its language.
     *
     * The old column is left as it was: this is a copy, not a move, so a rollback needs nothing but
     * `down()` and a re-run needs nothing but the unique index (which is why the insert ignores).
     */
    private function backfillTranslations(): void
    {
        $langByTerm = [];
        $ambiguousTerms = [];
        foreach (DB::table('term_translations')->orderBy('id')->get(['term_id', 'lang', 'is_primary']) as $row) {
            $termId = (string) $row->term_id;
            $lang = (string) $row->lang;
            if ((bool) $row->is_primary) {
                // Primary rows in two different languages: the reading code's order does not
                // separate them, so neither does this. Recorded, never guessed.
                if (isset($langByTerm[$termId]['primary']) && $langByTerm[$termId]['primary'] !== $lang) {
                    $ambiguousTerms[$termId] = true;
                }
                $langByTerm[$termId]['primary'] ??= $lang;
            }
            $langByTerm[$termId]['any'] ??= $lang;
        }

        $skipped = [];
        $rows = [];
        $now = now();
        foreach (DB::table('term_examples')->orderBy('id')
            ->get(['id', 'term_id', 'sentence_translation']) as $example) {
            $text = $example->sentence_translation;
            if (! is_string($text) || trim($text) === '') {
                continue;
            }

            $termId = (string) $example->term_id;
            $lang = $langByTerm[$termId]['primary'] ?? $langByTerm[$termId]['any'] ?? null;
            if ($lang === null || isset($ambiguousTerms[$termId])) {
                $skipped[] = ['example_id' => (string) $example->id, 'term_id' => $termId,
                    'why' => $lang === null ? 'term has no translation row' : 'primary translations in several languages'];

                continue;
            }

            $rows[] = [
                'id' => Ulid::generate(),
                'term_example_id' => (string) $example->id,
                'lang' => $lang,
                'text' => $text,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('example_translations')->insertOrIgnore($chunk);
        }

        // Said out loud rather than swallowed: a backfill that quietly leaves rows behind reads as
        // «moved everything» to whoever runs it, and these rows are precisely the ones a human has
        // to decide about.
        if ($skipped !== []) {
            Log::warning('example_translations backfill: rows left in the deprecated column', [
                'count' => count($skipped),
                'rows' => $skipped,
            ]);
        }
    }
};
