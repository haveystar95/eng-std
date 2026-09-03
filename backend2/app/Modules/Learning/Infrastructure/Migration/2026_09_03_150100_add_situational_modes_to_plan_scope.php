<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanKnobSupport;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** In the order of the session they serve: «Тебе скажут» → «Ты ответишь» → «Ты спросишь». */
    private const ADDED = [
        ExerciseMode::SituationalHear,
        ExerciseMode::SituationalSay,
        ExerciseMode::SituationalAsk,
    ];

    /**
     * THE SITUATIONAL TRAINERS GET THEIR PLAN-SCOPE ROWS, SWITCHED ON — one per (level, mode).
     *
     * «Рождается выключенным» is the release rule for a trainer that lands in somebody's ordinary
     * day (канон §12), and these do not: they have no global row at all, so nothing about the
     * learner's own trainer list moves. What a `scope='plan'` row configures is not «is this trainer
     * available» but «which numbers does the plan ladder run it on», and a level whose stage B is
     * empty is not a safer plan, it is a plan that cannot reach stage B. The plan matrix has shipped
     * switched ON at every level since it was introduced, for the same reason.
     *
     * All three at once, and the наряд says why: the rule «по одному тренажёру» exists so that each
     * new mechanic is seen in a live day before the next one is built — and this is ONE mechanic
     * dealt on three shelves, so one live day sees all of it (решение владельца, SIT-1).
     *
     * The rows they REPLACE stay in the table, and deliberately: `listening` still serves the word
     * ladder and `cloze` still serves it too — what changed is which CHECKLIST names them
     * ({@see PlanStageLadder}), which is code, not configuration.
     *
     * UPSERT rather than insert, exactly like the `description_match` migration: the migration that
     * seeded the plan scope walks {@see PlanStageLadder::allModes()}, so a database built from
     * scratch today already writes these three rows. Both histories must end at the same place.
     */
    public function up(): void
    {
        $this->dropGlobalRows();
        $position = $this->positionOf();

        foreach (PlanLevel::cases() as $level) {
            $knobs = PlanKnobs::shipped($level);
            foreach (self::ADDED as $mode) {
                $row = [
                    'enabled' => true,
                    'position' => $position[$mode->value],
                    'min_acquisition' => 'new',
                    'min_learning_step' => null,
                    'min_successful_reviews' => null,
                    'options_policy' => $knobs->optionsPolicy()->value,
                    'knobs' => json_encode($this->knobsFor($mode, $knobs), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ];

                $existing = DB::table('learning_mode_settings')
                    ->where('scope', 'plan')
                    ->where('level', $level->value)
                    ->where('mode', $mode->value);

                if ((clone $existing)->exists()) {
                    $existing->update($row);

                    continue;
                }

                DB::table('learning_mode_settings')->insert([
                    ...$row,
                    'id' => (string) Ulid::generate(),
                    'user_id' => null,
                    'scope' => 'plan',
                    'level' => $level->value,
                    'mode' => $mode->value,
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * NO GLOBAL ROW FOR A PLAN-ONLY TRAINER — deleted rather than shipped switched off, and this is
     * the one line of this migration that is not additive.
     *
     * The migration that exploded this table into one row per (scope, mode) walks the LIVE enum
     * ({@see \App\Modules\Learning\Infrastructure\Migration\...move_admission_matrix_into_mode_settings}),
     * so a database built from scratch TODAY writes a global row for each of these three the moment
     * they exist in code. A global row is not an inert switch: {@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission}
     * reads its rules out of this table, so the row IS the rule, and «switched off» is one tap in
     * the admin panel away from a situational card being dealt in a session that has no scene to
     * situate it in. There is nothing honest for that card to ask there.
     *
     * So the two histories are made to converge on the same place, which is «no row»: the owner's
     * database never had one, and a fresh one loses the one it was just given.
     */
    private function dropGlobalRows(): void
    {
        DB::table('learning_mode_settings')
            ->where('scope', 'global')
            ->whereIn('mode', array_map(static fn (ExerciseMode $m): string => $m->value, self::ADDED))
            ->delete();
    }

    /**
     * Where each new trainer sits in the plan registry — its index in the ladder's own mode list,
     * which is the order the seed used and therefore the order the admin screen already shows.
     * {@see PlanStageLadder::allModes()} walks the kinds in session order, so the three land as
     * hear → say → ask without this migration having to state a number.
     *
     * @return array<string, int>
     */
    private function positionOf(): array
    {
        $out = [];
        foreach (PlanStageLadder::allModes() as $index => $mode) {
            $out[$mode->value] = $index;
        }

        foreach (self::ADDED as $offset => $mode) {
            // A build where the ladder no longer names one of them: park it after everything else
            // rather than at position 0, which would walk it to the top of the screen.
            $out[$mode->value] ??= count($out) + $offset;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function knobsFor(ExerciseMode $mode, PlanKnobs $knobs): array
    {
        $all = $knobs->toArray();
        $out = [];
        foreach ([...PlanKnobSupport::appliedTo($mode), ...PlanKnobSupport::ignoredBy($mode)] as $name) {
            $out[$name] = $all[$name];
        }

        return $out;
    }

    public function down(): void
    {
        DB::table('learning_mode_settings')
            ->where('scope', 'plan')
            ->whereIn('mode', array_map(static fn (ExerciseMode $m): string => $m->value, self::ADDED))
            ->delete();
    }
};
