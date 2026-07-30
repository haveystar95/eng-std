<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Append-only triage log, kept SEPARATE from `reviews`: a swipe is a self-assessment,
    // not an exercise answer. Deliberately NO unique (user_id, term_id) — re-triage and
    // returning a word to learning are new rows; the current verdict is the latest by
    // decided_at. Idempotency comes from the client-generated ULID primary key.
    public function up(): void
    {
        Schema::create('term_triages', function (Blueprint $table) {
            $table->char('id', 26)->primary();                    // client-generated ULID
            $table->char('user_id', 26);
            $table->char('term_id', 26);
            $table->string('verdict', 8);                         // known | unknown | unsure
            $table->char('collection_id', 26)->nullable();        // source of the swipe; null on return-from-settings
            $table->integer('latency_ms')->nullable();
            $table->timestampTz('decided_at');
            $table->timestampTz('created_at')->nullable();        // server receipt time

            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
            $table->index(['user_id', 'term_id', 'decided_at'], 'term_triages_user_term_idx');
        });

        DB::statement("ALTER TABLE term_triages ADD CONSTRAINT term_triages_verdict_check CHECK (verdict IN ('known','unknown','unsure'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('term_triages');
    }
};
