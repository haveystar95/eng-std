<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance on generated content: WHICH PROMPT VERSION and WHICH MODEL produced this row.
 *
 * Until now a card carried no record of where it came from. `generation_requests.prompt_version`
 * says which prompt a *request* used, but terms are globally deduplicated and shared: the row a
 * learner reads may have been written by a request nobody can point at any more, merged from
 * several, or filled in later by the enrichment станок. When a class of defect is found — the lost
 * addressee («Tell us about…» → «Расскажите о…»), an example that merely repeats its term — the
 * first question is "which prompt wrote these, and how many rows are there", and there was no way
 * to ask it. That is what this adds, and it is a precondition for regenerating a *subset* of the
 * showcase rather than all of it.
 *
 * ## The sentinel: why existing rows are stamped 'legacy' rather than left NULL
 *
 * Both were live options. NULL-means-legacy needs no DML at all, which is attractive under a
 * content moratorium — but it spends the only signal the column has. After this migration NULL is
 * IMPOSSIBLE on an existing row, so a NULL appearing later means exactly one thing: a writer that
 * created content without stamping it. That is a bug this column can then catch by itself
 * (`WHERE prompt_version IS NULL`), and with NULL-means-legacy it would be indistinguishable from
 * ordinary old data forever. So: an explicit sentinel now, no DEFAULT on the column (a default
 * would re-hide the same bug behind a plausible value), and every writer stamps explicitly.
 *
 * The backfill writes only these new columns. It does not touch a single character of content —
 * no term, translation, example, variant or distractor text is read or changed by this migration.
 *
 * `'legacy'` means "written before provenance was recorded; its origin is not known" — which is
 * honest for every pre-existing row, whether it came from the станок, from a curator, or from the
 * owner typing it in.
 *
 * ## Why the enrichment products get only `generation_model`
 *
 * `term_accepted_variants` and `example_distractors` already carry `generator_version` — the same
 * fact under the older name, and the idempotency key of a станок run. Renaming it would churn a
 * live table and the code that reads it for no gain, so it stays; the model that produced the row
 * is what was missing beside it.
 */
return new class extends Migration
{
    /** Rows that predate provenance tracking. Deliberately not a column DEFAULT — see the docblock. */
    private const LEGACY = 'legacy';

    /** The content tables that get the full pair. */
    private const CONTENT_TABLES = ['terms', 'term_translations', 'term_examples'];

    /** The станок's products: they already version themselves, they just never said by which model. */
    private const ENRICHED_TABLES = ['term_accepted_variants', 'example_distractors'];

    public function up(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                // 16/64 match the widths already used for a prompt version and a model name
                // elsewhere in the schema (generation_requests, term_accepted_variants).
                $blueprint->string('prompt_version', 16)->nullable();
                $blueprint->string('generation_model', 64)->nullable();
            });

            // One statement, new column only. Not chunked: the whole store is a few hundred rows.
            DB::table($table)->whereNull('prompt_version')->update(['prompt_version' => self::LEGACY]);
        }

        foreach (self::ENRICHED_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('generation_model', 64)->nullable();
            });
        }

        // The access path this column exists for: "everything produced by prompt version X".
        // Partial, because the interesting queries are always about a NAMED version — the legacy
        // rows are the bulk and nobody asks for them by version.
        foreach (self::CONTENT_TABLES as $table) {
            DB::statement(
                "CREATE INDEX {$table}_prompt_version_idx ON {$table} (prompt_version) "
                . "WHERE prompt_version IS NOT NULL AND prompt_version <> '" . self::LEGACY . "'"
            );
        }
    }

    public function down(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_prompt_version_idx");
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['prompt_version', 'generation_model']);
            });
        }

        foreach (self::ENRICHED_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('generation_model');
            });
        }
    }
};
