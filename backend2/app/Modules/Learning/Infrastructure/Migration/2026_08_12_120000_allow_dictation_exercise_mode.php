<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `dictation` (hear the example sentence, write it down) joins the exercise modes, so the
     * reviews check constraint has to admit it — otherwise the first answer fails on insert.
     *
     * Note what this migration deliberately does NOT do: it does not add the mode to anyone's
     * enabled set. Per the release rule (CLAUDE.md), a new trainer ships switched OFF globally and
     * is turned on from the admin panel — for me first, then beta, then everyone. The panel lists
     * it the moment the enum knows about it, so nothing else is needed to find it there.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ('multiple_choice','word_bank','typing','listening','cloze','scramble','dictation'))");
    }

    public function down(): void
    {
        // Reversible only while no dictation answers exist; drop them first so the old, narrower
        // constraint can be restored instead of failing validation on live rows.
        DB::table('reviews')->where('exercise_mode', 'dictation')->delete();
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ('multiple_choice','word_bank','typing','listening','cloze','scramble'))");
    }
};
