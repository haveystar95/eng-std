<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ADDED = ['situational_hear', 'situational_say', 'situational_ask'];

    private const PREVIOUS = [
        'multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble',
        'dictation', 'pick_correct', 'speaking', 'description_match',
    ];

    /**
     * The three SITUATIONAL trainers join the exercise modes, so `reviews.exercise_mode` has to
     * admit them — otherwise the first tap fails on insert.
     *
     * No column widening this time: the longest of the three is `situational_hear` at 16 characters
     * and the column has been `varchar(24)` since `description_match` needed it.
     *
     * Unlike every trainer before them these get NO row in the global registry, and that is the
     * design rather than an omission (наряд SIT-1): a situational card asks about a MOMENT, and its
     * moment is assembled out of a plan day's scene. An ordinary session has no scene, so there is
     * nothing honest for the card to ask there, and {@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission::allows()}
     * is fail-closed — a mode with no rule is dealt nowhere. The rows they DO get are `scope='plan'`,
     * in the migration beside this one.
     */
    public function up(): void
    {
        $this->replaceCheck([...self::PREVIOUS, ...self::ADDED]);
    }

    public function down(): void
    {
        // Reversible only while no situational answers exist; drop them first so the narrower
        // constraint can be restored instead of failing on live rows.
        DB::table('reviews')->whereIn('exercise_mode', self::ADDED)->delete();
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
