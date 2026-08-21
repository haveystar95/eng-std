<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `description_match` trainer gets its row in the registry — one per scope, exactly like the
     * ten before it.
     *
     * Switched OFF, deliberately, in every scope INCLUDING the owner's. A new trainer ships dark and
     * is turned on себе → бете → всем from the admin panel: code in main is not a mode on anybody's
     * phone. The row still has to exist while switched off, because {@see ModeAdmission::allows()}
     * is fail-closed — a trainer with no row is undealable rather than universally available, and
     * the panel can only offer a toggle for something the table knows about.
     *
     * The threshold is the shipped one: `graduated`, no learning step, no reps — the assembly rung,
     * beside word_bank and cloze, and at its passport floor ({@see ModePassport}).
     *
     * `options_policy` is `standard`, not `distant`, and that is not an oversight. `distant` exists
     * for the recognition rungs, where the options are the SESSION's own neighbours because a
     * near-miss at a first meeting is a spelling test. This trainer's options come from the
     * distractor reader like an ordinary multiple_choice — and that reader already refuses a
     * candidate whose translations overlap the target's, which is the failure that actually matters
     * here (a description separates two words one Russian gloss has collapsed; offering both would
     * put two right answers on the card).
     *
     * UPSERT, not insert, for the reason the speaking migration spells out: the migration that
     * exploded this table into one row per (scope, mode) walks the LIVE enum, so on a database built
     * from scratch today it already writes a description_match row. Both histories must end at the
     * same place, so this states the end state instead of assuming which one it is walking into.
     */
    public function up(): void
    {
        $rule = ModeAdmission::shipped()->ruleFor(ExerciseMode::DescriptionMatch);
        $mode = ExerciseMode::DescriptionMatch->value;

        foreach ($this->scopes() as $userId => $position) {
            $scope = DB::table('learning_mode_settings')->where('mode', $mode);
            $userId !== '' ? $scope->where('user_id', $userId) : $scope->whereNull('user_id');

            if ((clone $scope)->first() !== null) {
                // Written by the explode migration on a fresh database; only the ordering — and the
                // switched-off state, which the release rule owns — are this migration's to state.
                $scope->update(['enabled' => false, 'position' => $position, 'updated_at' => now()]);

                continue;
            }

            DB::table('learning_mode_settings')->insert([
                'id' => (string) Ulid::generate(),
                'user_id' => $userId !== '' ? $userId : null,
                'mode' => $mode,
                'enabled' => false,
                'position' => $position,
                'min_acquisition' => $rule?->minAcquisition->value ?? Acquisition::Graduated->value,
                'min_learning_step' => $rule?->minLearningStep,
                'min_successful_reviews' => $rule?->minSuccessfulReviews,
                'options_policy' => $rule?->optionsPolicy->value ?? OptionsPolicy::Standard->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Every scope that has rows today, mapped to the position this trainer should take in it — one
     * past the last row that is NOT this trainer, so running it twice does not walk the row up the
     * list. A scope with no rows is not invented: that user inherits, and inheriting a switched-off
     * trainer is switched off.
     *
     * @return array<string, int>  scope key ('' = global) => the position for description_match
     */
    private function scopes(): array
    {
        $next = [];
        $rows = DB::table('learning_mode_settings')
            ->where('mode', '!=', ExerciseMode::DescriptionMatch->value)
            ->get(['user_id', 'position']);
        foreach ($rows as $row) {
            $key = (string) ($row->user_id ?? '');
            $next[$key] = max($next[$key] ?? 0, ((int) $row->position) + 1);
        }

        return $next === [] ? ['' => 0] : $next;
    }

    /** Reversible: the trainer leaves the registry entirely, in every scope. */
    public function down(): void
    {
        DB::table('learning_mode_settings')->where('mode', ExerciseMode::DescriptionMatch->value)->delete();
    }
};
