<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `scramble` (assemble the example sentence from word chips) joins the exercise modes, so the
     * reviews check constraint has to admit it — otherwise every scramble answer fails on insert.
     *
     * The column stays `varchar(16)`: the mode names were deliberately kept short for this reason
     * (`scramble` is 8), so no width change is needed here or for the two modes queued behind it.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ('multiple_choice','word_bank','typing','listening','cloze','scramble'))");
    }

    public function down(): void
    {
        // Reversible only while no scramble answers exist; drop them first so the old, narrower
        // constraint can be restored instead of failing validation on live rows.
        DB::table('reviews')->where('exercise_mode', 'scramble')->delete();
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ('multiple_choice','word_bank','typing','listening','cloze'))");
    }
};
