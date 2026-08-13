<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "A human (or the audit) looked at this sentence and decided it does not belong." Written the moment
 * a distractor is removed — by a proofreader's review or by `enrich:audit-distractors --apply` — so the
 * decision outlives the row it was about.
 *
 * Without this, deletion doesn't survive regeneration: the топап reads its dedup set from the LIVE
 * `example_distractors` rows (`EloquentEnrichmentTargetReader::byIds()`), so once a bad sentence is
 * deleted it is also invisible to the next run, and the model — asked the same question again — is free
 * to propose the exact same sentence back. `sentence` is stored CANONICALIZED (the shared
 * `LexicalNormalizer`), the same comparison the validator's dedup already runs candidates through, so a
 * re-proposed sentence differing only in case, punctuation or a contraction still matches.
 *
 * Keyed by `term_id`, not `example_id`: the pinned example itself can be regenerated, and the
 * suppression has to survive that — it is a fact about what was rejected for this TERM, not about a row
 * that may no longer exist.
 */
return new class extends Migration
{
    private const SOURCES = ['review', 'audit'];

    public function up(): void
    {
        Schema::create('enrichment_suppressions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->text('sentence');                        // canonicalize()d, comparable directly
            $table->string('source', 16);                     // review | audit
            $table->timestampTz('created_at');

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            // One suppression per (term, sentence): a second removal of the same sentence is a no-op.
            $table->unique(['term_id', 'sentence'], 'enrichment_suppressions_uidx');
        });

        $sources = "'" . implode("','", self::SOURCES) . "'";
        DB::statement("ALTER TABLE enrichment_suppressions ADD CONSTRAINT enrichment_suppressions_source_check CHECK (source IN ({$sources}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('enrichment_suppressions');
    }
};
