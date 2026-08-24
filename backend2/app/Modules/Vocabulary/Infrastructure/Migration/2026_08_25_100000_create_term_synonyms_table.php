<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `term_synonyms` — near-synonyms of the term ON THE STUDIED SIDE: `purpose` → `goal`, `aim`.
 *
 * ## Why this is not `term_accepted_variants`
 *
 * The two look alike and mean different things, and the difference decides what a trainer may do
 * with each. A VARIANT is another SPELLING of the same word — `organise`/`organize`,
 * `check-in`/`check in` — so it is accepted everywhere the term is typed, including a card whose
 * question was the SOUND of the word. A SYNONYM is a different word with nearly the same meaning,
 * so it answers a question about MEANING («цель» → say an English word for it) and does not answer
 * a question about this word («you heard /ˈpɜːpəs/, write it»). Storing the two in one table would
 * make that distinction unrepresentable, and the looser of the two readings would win — a learner
 * typing `goal` into a listening card would be marked right for a word they did not hear.
 *
 * The registry decision (SYN-1) also says it plainly: separate ROWS, never one string with a
 * separator. A delimiter column cannot be deduplicated, cannot be indexed, cannot carry a source,
 * and cannot have one member removed by a proofreader without a rewrite of the whole cell.
 *
 * ## Why there is no `term_translations` change beside it
 *
 * There is nothing to change. `term_translations` has always been many rows per `(term, lang)` —
 * `term_translations_uidx` is unique on `(term_id, lang, text)`, not on `(term_id, lang)` — and
 * exactly one of them per language is pinned by `is_primary`
 * ({@see \App\Modules\Vocabulary\Domain\Entity\Term::addTranslation()}, the A7 rule). Multiple
 * translations of one term into one language are therefore already representable, already stored
 * (18 of the 705 live `(term, lang)` pairs hold more than one) and already read in a defined order
 * ({@see \App\Modules\Vocabulary\Infrastructure\Eloquent\TranslationPick}). SYN-1 Ч.1 п. 2 asks for
 * the fact to be established and for no new table if the schema already allows it; it does.
 *
 * ## Columns
 *
 *  - `lang` is the TERM's language, not the learner's. It is denormalised onto the row rather than
 *    joined from `terms` because every reader of this table is asking a same-language question
 *    («is this candidate a synonym of that term») and a synonym filed under the wrong language is a
 *    defect a NOT NULL column can at least be audited for.
 *  - `source` separates what the станок proposed (`auto`) from what a person pinned (`curated`), so
 *    a later re-run can be told to leave the curated rows alone. Closed set, held by a CHECK for
 *    the same reason `example_distractors.error_type` is.
 *
 * Unique on `(term_id, text)` — the same shape as `term_accepted_variants_uidx` — so a re-run of
 * the станок is an `insertOrIgnore` no-op rather than a duplicate, and a curated row survives it.
 */
return new class extends Migration
{
    private const SOURCES = ['auto', 'curated'];

    public function up(): void
    {
        Schema::create('term_synonyms', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->text('text');
            $table->string('lang', 5);
            $table->string('source', 16);
            $table->timestampsTz();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->unique(['term_id', 'text'], 'term_synonyms_uidx');
            $table->index('term_id', 'term_synonyms_term_idx');
        });

        $sources = "'" . implode("','", self::SOURCES) . "'";
        DB::statement("ALTER TABLE term_synonyms ADD CONSTRAINT term_synonyms_source_check CHECK (source IN ({$sources}))");

        // The access path the multiple-choice distractor filter takes: «is any of these candidate
        // TEXTS a synonym of anything». It reads by text across terms, which the unique index above
        // cannot serve (its leading column is `term_id`).
        DB::statement('CREATE INDEX term_synonyms_lang_text_idx ON term_synonyms (lang, lower(text))');
    }

    public function down(): void
    {
        Schema::dropIfExists('term_synonyms');
    }
};
