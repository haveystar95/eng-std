<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The станок's third and fourth products, which are NOT content: they are things a human has to
 * look at. Two of the four checks can only end in "a person decides" —
 *
 *  - `ambiguity` — the back-translation of the Russian prompt does not land on the stored example
 *    and the accepted variants don't cover the gap, i.e. the card is unanswerable as written.
 *    Candidate for regeneration, never auto-rewritten.
 *  - `language` — UA lexis in a Russian field, or non-English in an English field. This is the
 *    generalisation of the one-off «здаватися» scan, which is why no separate scan is needed.
 *  - `variant_conflict` — a proposed correct variant collided with a proposed wrong distractor.
 *    The distractor row is dropped by the validator; the TERM lands here because one of the two
 *    claims is wrong and only a human can say which.
 *
 * Append-only: a run records what it saw. Re-reading a finding is a report, not a state machine —
 * resolving one means editing the content and re-running, which writes a fresh row at a new version.
 */
return new class extends Migration
{
    private const KINDS = ['ambiguity', 'language', 'variant_conflict'];

    public function up(): void
    {
        Schema::create('enrichment_findings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->string('kind', 24);
            $table->string('field', 32)->nullable();   // which field the language flag is about
            $table->text('detail');                    // human-readable: what looked wrong
            $table->string('generator_version', 16);
            $table->timestampTz('created_at');

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->index(['generator_version', 'kind'], 'enrichment_findings_run_idx');
            $table->index('term_id', 'enrichment_findings_term_idx');
        });

        $kinds = "'" . implode("','", self::KINDS) . "'";
        DB::statement("ALTER TABLE enrichment_findings ADD CONSTRAINT enrichment_findings_kind_check CHECK (kind IN ({$kinds}))");

        // The idempotency key of a run, and the reason it is a table rather than an inference from
        // the content: "this term produced no variants" and "this term was never processed" look
        // identical if you only look at `term_accepted_variants`. Without the mark, every clean
        // term would be re-sent to the model on every re-run — paying again for the same nothing.
        Schema::create('term_enrichment_versions', function (Blueprint $table): void {
            $table->char('term_id', 26);
            $table->string('generator_version', 16);
            $table->timestampTz('created_at');

            $table->primary(['term_id', 'generator_version']);
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_enrichment_versions');
        Schema::dropIfExists('enrichment_findings');
    }
};
