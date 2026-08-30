<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two facts a plan needs about shared content, added to the shared tables rather than to a
     * plan-only side table — because both are facts about the LANGUAGE, not about one learner's
     * plan, and a term is global.
     *
     * ## `terms.is_line` — what the expression DOES
     *
     * Orthogonal to `type`, which says what it IS. «payment module» and «server-side» are
     * `type: phrase` by grammar and substitutions by function; «My back has been hurting for a
     * week.» is a `phrase` too and is a spoken turn. The sandbox (docs/research/
     * plan-sandbox-2026-08-29.md §7.5) found the two questions collapsed into one field and the
     * answer to both coming out wrong: multi-word substitutions had to be called `word`, which was
     * a lie about the language, in order to say the true thing about the function.
     *
     * Default FALSE, and no backfill: every term written before a plan existed came out of
     * `generate_collection`, which never asked the question. Silence is «not known to be a reply»,
     * which is exactly right and is not the same as «known not to be».
     *
     * ## `terms.difficulty_score` — the day's ordering key
     *
     * Written by DifficultyScorer at import. Nullable because it is an OPINION about a term that a
     * caller may not have formed: a term the plan never touched has no score, and ordering falls
     * back to the day's own order. It is deliberately NOT a CEFR level — `cefr` says how advanced
     * the word is in the language, this says how much machinery a sentence carries, and a plan
     * orders its day by the second.
     *
     * ## `term_examples.scope_collection_id` — an example that belongs to ONE day
     *
     * The plan's re-use rule. A term the learner already met on day 1 is NOT re-taught on day 3:
     * the model writes it a fresh example set in day 3's situation and nothing else. Those
     * sentences are true of day 3 and would be noise on the term's general card — «Veterinarul l-a
     * ascultat pe motanul meu înainte de vaccin» is not a good general example of `motanul`, it is
     * a good example of `motanul` AT THE VET.
     *
     * NULL means what it has always meant: a general example, shown anywhere. The column is
     * additive in the strongest sense — every existing row is NULL and every existing query that
     * does not mention it keeps returning exactly what it returned yesterday.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->boolean('is_line')->default(false)->after('type');
            $table->integer('difficulty_score')->nullable()->after('frequency_rank');
        });

        Schema::table('term_examples', function (Blueprint $table): void {
            $table->char('scope_collection_id', 26)->nullable()->after('source');

            // CASCADE, deliberately, and not null-on-delete: a day-scoped sentence is ABOUT that
            // day. Surviving its collection as a general example would silently promote «в машине
            // он кашлял два раза сегодня» onto the term's card for every learner.
            $table->foreign('scope_collection_id')->references('id')->on('collections')->cascadeOnDelete();
        });

        // The access path this column exists for: «all examples scoped to this day». Partial,
        // because the overwhelming majority of rows are and will stay NULL.
        DB::statement('CREATE INDEX term_examples_scope_idx ON term_examples (scope_collection_id) WHERE scope_collection_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS term_examples_scope_idx');

        Schema::table('term_examples', function (Blueprint $table): void {
            $table->dropForeign(['scope_collection_id']);
            $table->dropColumn('scope_collection_id');
        });

        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn(['is_line', 'difficulty_score']);
        });
    }
};
