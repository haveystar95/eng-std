<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the language barrier threw away, and why.
 *
 * A term whose Russian field came back in Ukrainian is not written — it is re-asked, and if the
 * model still cannot answer in the requested language it is dropped. Dropping it silently would
 * make an under-delivered collection indistinguishable from a model that simply produced fewer
 * items, which is exactly the ambiguity that let the first leak run for a week unnoticed
 * (docs/ua-audit.md). So the drop leaves a row.
 *
 * Append-only, like `enrichment_findings`: a run records what it refused. There is no term_id —
 * the whole point is that no term was created — so the text is stored verbatim.
 */
return new class extends Migration
{
    /** Which field of the item failed. Mirrors the enrichment finding's `field`. */
    private const FIELDS = ['translation', 'example_translation', 'text', 'example'];

    public function up(): void
    {
        Schema::create('generation_rejections', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('request_id', 26);
            $table->text('text');                    // the target-language item that was refused
            $table->string('field', 32);             // which of its fields was in the wrong language
            $table->text('reason');                  // human-readable, in the operator's language
            $table->smallInteger('attempts');        // repair calls spent before giving up
            $table->timestampTz('created_at');

            $table->foreign('request_id')->references('id')->on('generation_requests')->cascadeOnDelete();
            $table->index('request_id', 'generation_rejections_request_idx');
        });

        $fields = "'" . implode("','", self::FIELDS) . "'";
        DB::statement("ALTER TABLE generation_rejections ADD CONSTRAINT generation_rejections_field_check CHECK (field IN ({$fields}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_rejections');
    }
};
