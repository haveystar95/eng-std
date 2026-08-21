<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODES = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct', 'speaking', 'description_match'];

    private const PREVIOUS = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct', 'speaking'];

    /**
     * `description_match` (read what a word MEANS, pick the word) joins the exercise modes, so the
     * reviews check constraint has to admit it — otherwise the first answer fails on insert.
     *
     * Like every trainer since `dictation`, this migration does NOT enable the mode for anyone: the
     * registry row lands switched off in the migration beside it, per the release rule (себе → бете
     * → всем, from the admin panel). `config/learning.php` stays untouched.
     *
     * IT ALSO WIDENS THE COLUMN, which no previous mode migration had to. `description_match` is 17
     * characters and `reviews.exercise_mode` is `varchar(16)` — one short. Every trainer added so
     * far («speaking», 8; «pick_correct», 12) fitted, so the limit had never been tested; without
     * this the mode would pass the CHECK and still fail the insert, on the learner's first answer.
     * 24 to match `learning_mode_settings.mode`, which the admission-matrix migration already sized
     * for names of this length.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE reviews ALTER COLUMN exercise_mode TYPE varchar(24)');
        $this->replaceCheck(self::MODES);
    }

    public function down(): void
    {
        // Reversible only while no description_match answers exist; drop them first so the narrower
        // constraint can be restored instead of failing on live rows.
        DB::table('reviews')->where('exercise_mode', 'description_match')->delete();
        $this->replaceCheck(self::PREVIOUS);
        DB::statement('ALTER TABLE reviews ALTER COLUMN exercise_mode TYPE varchar(16)');
    }

    /** @param  list<string>  $modes */
    private function replaceCheck(array $modes): void
    {
        $list = "'" . implode("','", $modes) . "'";
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ({$list}))");
    }
};
