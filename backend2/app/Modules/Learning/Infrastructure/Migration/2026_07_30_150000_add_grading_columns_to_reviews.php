<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The server grades now, so a review records HOW it was answered, not just the outcome.
    //  - exercise_mode : which trainer produced it (drives the per-mode latency median).
    //  - is_correct    : kept alongside the derived grade so analytics can tell "wrong" from
    //                    "right but slow" without re-deriving thresholds after they move.
    //  - is_practice   : free-practice answers keep the streak and stay replayable, but never
    //                    touch scheduling and are excluded from the latency median and retention.
    //  - response      : the raw text the user typed — free signal for growing the synonym list
    //                    and tuning the generation prompt.
    // All nullable/defaulted, so the append-only log's existing rows stay valid.
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('exercise_mode', 16)->nullable()->after('grade');
            $table->boolean('is_correct')->nullable()->after('exercise_mode');
            $table->boolean('is_practice')->default(false)->after('is_correct');
            $table->text('response')->nullable()->after('is_practice');
        });

        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ('multiple_choice','word_bank','typing','listening','cloze'))");
        // The hot path for the per-mode median: correct, non-practice answers of one mode.
        DB::statement('CREATE INDEX reviews_latency_idx ON reviews (user_id, exercise_mode) WHERE is_correct AND NOT is_practice');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reviews_latency_idx');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['exercise_mode', 'is_correct', 'is_practice', 'response']);
        });
    }
};
