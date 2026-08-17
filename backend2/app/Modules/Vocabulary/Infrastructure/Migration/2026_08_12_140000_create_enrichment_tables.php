<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two products of the enrichment станок that are CONTENT (so Vocabulary owns them, not
 * Generation — Generation only fills them in through Vocabulary's Application):
 *
 *  - `term_accepted_variants` — additional CORRECT target-language answers for a term. This is the
 *    `term_forms` table EloquentTermAnswerKeyReader has been promising: the answer key stops being
 *    a one-element set. It is graded against on the server AND mirrored to the device, because
 *    offline typed grading has to agree with the server about what counts as right.
 *  - `example_distractors` — deliberately WRONG variants of a pinned example sentence, each with
 *    the error typed and located. Written now, consumed later: the `find_the_mistake` trainer is
 *    not built in this pass, so nothing reads these yet. The schema carries what that mode will
 *    need (`error_span` + `correction` for the reveal) so the станок doesn't have to run twice.
 *
 * Both carry `generator_version` — that is the idempotency key of a batch run: a term already
 * enriched at this version is skipped rather than re-paid for.
 */
return new class extends Migration
{
    /** Typical RU-speaker error classes. A closed set: the report groups by it, so free text would rot. */
    private const ERROR_TYPES = ['article', 'preposition', 'tense', 'word_order', 'false_friend', 'modal_to'];

    public function up(): void
    {
        Schema::create('term_accepted_variants', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->text('text');                            // a correct alternative answer
            $table->text('note')->nullable();                // why it's equivalent (proofreading aid)
            $table->string('generator_version', 16);
            $table->timestampsTz();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            // Dedup per term, so a re-run at a NEW version can't double the same variant.
            $table->unique(['term_id', 'text'], 'term_accepted_variants_uidx');
            $table->index('term_id', 'term_accepted_variants_term_idx');
        });

        Schema::create('example_distractors', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('example_id', 26);
            $table->text('sentence');                        // the wrong sentence shown as an option
            $table->string('error_type', 24);
            $table->text('error_span');                      // the wrong fragment, VERBATIM from `sentence`
            $table->text('correction');                      // what that span should have been
            $table->string('generator_version', 16);
            $table->timestampsTz();

            $table->foreign('example_id')->references('id')->on('term_examples')->cascadeOnDelete();
            $table->unique(['example_id', 'sentence'], 'example_distractors_uidx');
            $table->index('example_id', 'example_distractors_example_idx');
        });

        $types = "'" . implode("','", self::ERROR_TYPES) . "'";
        DB::statement("ALTER TABLE example_distractors ADD CONSTRAINT example_distractors_error_type_check CHECK (error_type IN ({$types}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('example_distractors');
        Schema::dropIfExists('term_accepted_variants');
    }
};
