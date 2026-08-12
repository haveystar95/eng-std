<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODES = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct'];

    private const PREVIOUS = ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation'];

    /**
     * `pick_correct` (three sentences, pick the right one) joins the exercise modes, so the reviews
     * check constraint has to admit it — otherwise the first answer fails on insert.
     *
     * As with `dictation`, this migration deliberately does NOT enable the mode for anyone. Per the
     * release rule (CLAUDE.md) a new trainer ships switched OFF globally and is turned on from the
     * admin panel — me first, then beta, then everyone. `config/learning.php` is untouched; the panel
     * lists the mode as soon as the enum knows it, which is all that is needed to find it there.
     *
     * `varchar(16)` still fits: `pick_correct` is 12 characters (finding Б1 of the trainers-v3 spec).
     */
    public function up(): void
    {
        $this->replaceCheck(self::MODES);
    }

    public function down(): void
    {
        // Reversible only while no pick_correct answers exist; drop them first so the narrower
        // constraint can be restored instead of failing on live rows.
        DB::table('reviews')->where('exercise_mode', 'pick_correct')->delete();
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
