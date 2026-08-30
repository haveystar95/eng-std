<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanKnobSupport;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PLAN'S OWN TRAINER SETTINGS — a second SCOPE in the table that already holds the first.
     *
     * `learning_mode_settings` has been one row per (user scope, mode) since the admission matrix
     * moved into it. A plan needs a THIRD dimension the ordinary trainer settings have no use for:
     * the learner's LEVEL. `zero` gets three options with distant distractors and the first letter
     * of the word they are typing; `fluent` gets four options a hair apart and nothing else. Same
     * trainers, same ladder, different numbers — which is the definition of configuration, so it
     * goes in the table configuration lives in rather than into constants in a service.
     *
     * The two scopes never mix and the readers say so explicitly:
     *
     *   `scope='global'`  what the app already had. `level` is NULL, `user_id` NULL is the product
     *                     default and a user id overrides it per mode.
     *   `scope='plan'`    one row per (level, mode-in-the-plan-ladder), `user_id` NULL. It carries
     *                     the KNOBS and an on/off switch for that trainer inside the plan ladder.
     *
     * Adding a scope to a table two readers already query is the one dangerous part of this, and it
     * is handled at the source: {@see \App\Modules\Learning\Infrastructure\Eloquent\EloquentEnabledModesReader}
     * and its writer now filter `scope='global'`. Without that filter a plan row would appear in the
     * ordinary rotation as an eleventh copy of `speaking`.
     *
     * ## Why the knobs are stored per MODE and not per level
     *
     * Six knobs, four levels — the obvious shape is four rows. It is the wrong one: a knob belongs
     * to the trainer that reads it (`cloze_blanks` is a fact about the cloze card), and the row the
     * admin screen shows is a trainer. So each row carries exactly the knobs its own mode names —
     * which is also what makes «выставлено, но игнорируется» legible: the `dictation` row says
     * `tts_rate: slow` and {@see PlanKnobSupport} says nothing reads it yet. A level's full knob set
     * is the merge of its rows, done once in the reader.
     */
    public function up(): void
    {
        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->string('scope', 8)->default('global')->after('id');
            $table->string('level', 16)->nullable()->after('scope');
            // Only the knobs this row's own mode names. NULL on every global row — the ordinary
            // trainer settings have no level to be tuned for.
            $table->jsonb('knobs')->nullable()->after('options_policy');
        });

        DB::statement("ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_scope_check CHECK (scope IN ('global','plan'))");
        DB::statement("ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_level_check CHECK (level IS NULL OR level IN ('zero','basic','conversational','fluent'))");
        // The level is what makes a plan row a plan row: a plan row without one could not be found
        // by the reader, and a global row with one would be a setting nobody reads.
        DB::statement("ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_plan_level_check CHECK ((scope = 'plan') = (level IS NOT NULL))");
        // No per-user plan settings yet. When one learner needs their own knobs this constraint is
        // the thing to drop — deliberately, rather than discovering that half the code assumed it.
        DB::statement("ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_plan_global_check CHECK (scope = 'global' OR user_id IS NULL)");

        DB::statement('DROP INDEX IF EXISTS learning_mode_settings_scope_mode_uidx');
        DB::statement(
            'CREATE UNIQUE INDEX learning_mode_settings_scope_mode_uidx ON learning_mode_settings '
            . "(COALESCE(user_id::text, ''), scope, COALESCE(level, ''), mode)"
        );

        $this->seedPlanRows();
    }

    /**
     * One row per (level, mode of the plan ladder). Switched ON — unlike a new TRAINER, which ships
     * dark and is turned on себе → бете → всем, these rows are not a trainer but the settings a plan
     * runs the existing trainers on, and a plan whose every stage is empty is not a safer plan, it
     * is a broken one. The trainer's own global toggle still has the last word: a mode switched off
     * for the learner falls out of the plan checklist the same way an inapplicable one does.
     */
    private function seedPlanRows(): void
    {
        $rows = [];
        foreach (PlanLevel::cases() as $level) {
            $knobs = PlanKnobs::shipped($level);
            foreach (PlanStageLadder::allModes() as $position => $mode) {
                $rows[] = [
                    'id' => (string) Ulid::generate(),
                    'user_id' => null,
                    'scope' => 'plan',
                    'level' => $level->value,
                    'mode' => $mode->value,
                    'enabled' => true,
                    'position' => $position,
                    // The admission columns are the GLOBAL ladder's vocabulary and the plan does not
                    // use them: which trainer comes next is PlanStageLadder's answer, not this row's.
                    // They are filled with the widest possible value rather than left to a default
                    // that would read as a threshold somebody chose.
                    'min_acquisition' => 'new',
                    'min_learning_step' => null,
                    'min_successful_reviews' => null,
                    // The one admission column the plan DOES use, because `distractor_closeness` is
                    // this same setting under the plan's own name ({@see PlanKnobs::optionsPolicy}).
                    'options_policy' => $knobs->optionsPolicy()->value,
                    'knobs' => json_encode($this->knobsFor($mode, $knobs), JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('learning_mode_settings')->insert($rows);
    }

    /**
     * The knobs THIS mode names — the ones it reads today plus the ones it is configured with and
     * ignores. Both, because the second list is the задел and hiding it would make a future trainer
     * change look like a new feature instead of a switch being honoured.
     *
     * @return array<string, mixed>
     */
    private function knobsFor(ExerciseMode $mode, PlanKnobs $knobs): array
    {
        $all = $knobs->toArray();
        $names = [...PlanKnobSupport::appliedTo($mode), ...PlanKnobSupport::ignoredBy($mode)];

        $out = [];
        foreach ($names as $name) {
            $out[$name] = $all[$name];
        }

        return $out;
    }

    public function down(): void
    {
        DB::table('learning_mode_settings')->where('scope', 'plan')->delete();

        foreach (['scope', 'level', 'plan_level', 'plan_global'] as $name) {
            DB::statement("ALTER TABLE learning_mode_settings DROP CONSTRAINT IF EXISTS learning_mode_settings_{$name}_check");
        }
        DB::statement('DROP INDEX IF EXISTS learning_mode_settings_scope_mode_uidx');

        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->dropColumn(['scope', 'level', 'knobs']);
        });

        DB::statement("CREATE UNIQUE INDEX learning_mode_settings_scope_mode_uidx ON learning_mode_settings (COALESCE(user_id::text, ''), mode)");
    }
};
