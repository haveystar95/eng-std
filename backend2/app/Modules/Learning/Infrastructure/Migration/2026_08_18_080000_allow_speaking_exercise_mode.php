<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODES = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct', 'speaking'];

    private const PREVIOUS = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct'];

    /**
     * `speaking` (say it out loud; the device recognises the speech on-device) joins the exercise
     * modes, so the reviews check constraint has to admit it — otherwise the first answer fails on
     * insert.
     *
     * Like `dictation` and `pick_correct` before it, this migration does NOT enable the mode for
     * anyone: the registry row lands switched off in the migration beside this one, per the release
     * rule (себе → бете → всем, from the admin panel). `config/learning.php` stays untouched.
     *
     * `varchar(16)` still fits: `speaking` is 8 characters.
     */
    public function up(): void
    {
        $this->replaceCheck(self::MODES);
    }

    public function down(): void
    {
        // Reversible only while no speaking answers exist; drop them first so the narrower
        // constraint can be restored instead of failing on live rows.
        DB::table('reviews')->where('exercise_mode', 'speaking')->delete();
        $this->replaceCheck(self::PREVIOUS);
    }

    /** @param  list<string>  $modes */
    private function replaceCheck(array $modes): void
    {
        $list = "'" . implode("','", $modes) . "'";
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ({$list}))");
    }
};
