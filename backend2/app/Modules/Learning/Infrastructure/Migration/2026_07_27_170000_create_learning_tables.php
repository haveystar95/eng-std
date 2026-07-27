<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Progress is keyed by (user_id, term_id) — never by collection. A term learned in
        // one collection is learned everywhere.
        Schema::create('user_term_progress', function (Blueprint $table) {
            $table->char('user_id', 26);
            $table->char('term_id', 26);
            $table->string('state', 16);                          // new | learning | review | relearning
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->integer('interval_days')->default(0);
            $table->timestampTz('due_at')->nullable();
            $table->integer('reps')->default(0);
            $table->integer('lapses')->default(0);
            $table->timestampTz('last_reviewed_at')->nullable();
            $table->timestampsTz();

            $table->primary(['user_id', 'term_id']);
            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE user_term_progress ADD CONSTRAINT user_term_progress_state_check CHECK (state IN ('new','learning','review','relearning'))");
        // The hot query: due cards at session start (state <> 'new', due_at <= now, ordered).
        DB::statement("CREATE INDEX user_term_progress_due_idx ON user_term_progress (user_id, due_at) WHERE state <> 'new'");
        DB::statement("CREATE INDEX user_term_progress_new_idx ON user_term_progress (user_id) WHERE state = 'new'");

        Schema::create('study_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('collection_id', 26)->nullable();        // cross-module ref, intentionally no FK
            $table->string('mode', 16);                           // flashcard | typing | multiple_choice | listening
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->jsonb('stats')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'started_at'], 'study_sessions_user_idx');
        });

        DB::statement("ALTER TABLE study_sessions ADD CONSTRAINT study_sessions_mode_check CHECK (mode IN ('flashcard','typing','multiple_choice','listening'))");

        // Append-only event log. Primary key is the CLIENT-generated ULID, so a re-uploaded
        // batch is an ignored insert (ON CONFLICT DO NOTHING). Never updated.
        Schema::create('reviews', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('term_id', 26);
            $table->char('session_id', 26)->nullable();
            $table->string('grade', 8);                           // again | hard | good | easy
            $table->integer('latency_ms')->nullable();
            $table->timestampTz('answered_at');
            $table->timestampTz('created_at')->nullable();        // server receipt time

            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('study_sessions')->nullOnDelete();
            $table->index(['user_id', 'answered_at'], 'reviews_user_answered_idx');
        });

        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_grade_check CHECK (grade IN ('again','hard','good','easy'))");

        // Read model, rebuildable by replaying `reviews`. Any stats bug is a replay.
        Schema::create('daily_user_stats', function (Blueprint $table) {
            $table->char('user_id', 26);
            $table->date('date');
            $table->integer('reviews_count')->default(0);
            $table->integer('new_terms_count')->default(0);
            $table->integer('correct_count')->default(0);
            $table->integer('study_seconds')->default(0);

            $table->primary(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_user_stats');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('user_term_progress');
    }
};
