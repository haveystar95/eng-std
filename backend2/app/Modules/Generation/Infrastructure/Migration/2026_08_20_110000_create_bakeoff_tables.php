<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The bake-off SANDBOX: everything a provider comparison produces, kept where nothing that serves a
 * learner can reach it.
 *
 * ## Separate tables, not a separate schema
 *
 * A Postgres schema (`bakeoff.*`) was the other option and buys nothing here. The isolation that
 * matters is that no reader of content ever selects from these tables, and that is already true by
 * construction: they are new, and only the bake-off command and its report touch them. A second
 * schema would add `search_path` handling to every connection and migration for a guarantee we
 * already have — while the actual risk, a run that writes into `terms`, is prevented by the runner
 * never calling a Vocabulary write command at all, which no schema boundary would have enforced.
 *
 * Nothing here has a foreign key into `terms`. `source_term_id` is a plain reference, deliberately:
 * a candidate is a note about a term, not a child of it, and a sandbox row must never be able to
 * block or cascade a change to live content.
 *
 * ## Three tables
 *
 *  - `bakeoff_runs` — one comparison, with the prompt version it was run under;
 *  - `bakeoff_calls` — one vendor call: what it cost, how long it took, whether it answered at all.
 *    Separate from the candidates because latency and spend are facts about the CALL, and copying
 *    them onto every item would make one slow call look like twelve;
 *  - `bakeoff_candidates` — one produced item, as produced, with its check verdict beside it.
 */
return new class extends Migration
{
    /** A/B/C — the three pipelines being compared. Closed set: the report groups by it. */
    private const TRACKS = ['a', 'b', 'c'];

    public function up(): void
    {
        Schema::create('bakeoff_runs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('label', 64);              // what this run was, in one phrase
            $table->string('prompt_version', 16);
            $table->string('source_lang', 5);
            $table->string('target_lang', 5);
            $table->jsonb('notes')->nullable();       // provider availability, sample composition
            $table->timestampTz('created_at');
        });

        Schema::create('bakeoff_calls', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('run_id', 26);
            $table->string('track', 1);
            $table->string('provider', 16);
            $table->string('model', 64);
            $table->string('shape', 16);
            $table->char('prompt_sha', 64);           // WHICH bytes were sent, not just which version
            $table->text('task_key');                 // the topic, or the term this call was about
            $table->integer('latency_ms')->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 12, 6)->nullable();
            $table->boolean('ok');
            // A failed call is DATA: a provider that dies on a third of the sample has told us
            // something the successful two-thirds would otherwise hide.
            $table->text('error')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('run_id')->references('id')->on('bakeoff_runs')->cascadeOnDelete();
            $table->index(['run_id', 'track', 'provider'], 'bakeoff_calls_run_idx');
        });

        Schema::create('bakeoff_candidates', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('run_id', 26);
            $table->char('call_id', 26);
            $table->string('track', 1);
            $table->string('provider', 16);
            $table->integer('position');              // place in the answer — the degradation axis
            $table->char('source_term_id', 26)->nullable();   // NOT a foreign key — see the docblock
            $table->text('term_text')->nullable();    // the term this item was asked to render
            $table->jsonb('payload');                 // the item exactly as produced
            $table->jsonb('checks');                  // {failed: [...], notes: {...}}
            $table->boolean('clean');
            $table->timestampTz('created_at');

            $table->foreign('run_id')->references('id')->on('bakeoff_runs')->cascadeOnDelete();
            $table->foreign('call_id')->references('id')->on('bakeoff_calls')->cascadeOnDelete();
            $table->index(['run_id', 'track', 'provider'], 'bakeoff_candidates_run_idx');
            // The report's other access path: "show me everything that failed", ordered.
            $table->index(['run_id', 'clean'], 'bakeoff_candidates_clean_idx');
        });

        $tracks = "'" . implode("','", self::TRACKS) . "'";
        DB::statement("ALTER TABLE bakeoff_calls ADD CONSTRAINT bakeoff_calls_track_check CHECK (track IN ({$tracks}))");
        DB::statement("ALTER TABLE bakeoff_candidates ADD CONSTRAINT bakeoff_candidates_track_check CHECK (track IN ({$tracks}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bakeoff_candidates');
        Schema::dropIfExists('bakeoff_calls');
        Schema::dropIfExists('bakeoff_runs');
    }
};
