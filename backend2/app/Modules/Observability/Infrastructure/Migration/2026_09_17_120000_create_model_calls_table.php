<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE JOURNAL OF MODEL CALLS (наряд GEN-3): one row per call to a text model, written BEFORE the call and finished after
     * it. `api_request_logs` records a call only when it came back (or broke in a way the client saw) — a worker killed
     * mid-call left no row there at all, and a call our client dropped at its timeout was billed by the vendor and
     * visible nowhere as money. Here such a call stays, `lost`.
     *
     * Read by the day (`started_at`) for the reconciliation with the vendor's invoice, and by `(status, started_at)` for
     * the sweep that marks the calls of dead processes lost.
     */
    public function up(): void
    {
        Schema::create('model_calls', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('status', 16);                          // started | completed | failed | lost
            $table->string('provider', 32);                        // openai | anthropic | xai | gemini
            $table->string('model', 100);                          // the model asked for
            $table->string('answered_model', 100)->nullable();     // the model the vendor says answered
            $table->string('purpose', 32)->nullable();             // what the spend is for — as api_request_logs.purpose
            $table->integer('estimated_tokens_in');                // before the call, from the request body
            $table->integer('tokens_in')->nullable();              // the vendor's usage
            $table->integer('cached_tokens')->nullable();          // of tokens_in, served from the vendor's prompt cache
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();        // ModelCost, cached tokens at their cached rate
            $table->integer('http_status')->nullable();
            $table->text('error')->nullable();
            $table->integer('timeout_seconds');                    // how long the caller waited for the answer
            $table->integer('latency_ms')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
        });

        DB::statement("ALTER TABLE model_calls ADD CONSTRAINT model_calls_status_check CHECK (status IN ('started','completed','failed','lost'))");
        DB::statement('CREATE INDEX model_calls_started_idx ON model_calls (started_at)');
        DB::statement('CREATE INDEX model_calls_status_started_idx ON model_calls (status, started_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('model_calls');
    }
};
