<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admission matrix becomes DATA, in the table that already holds the trainer toggles.
     *
     * `learning_mode_settings` was one row per SCOPE holding an ordered JSON array of enabled mode
     * names. It becomes one row per (SCOPE, MODE), which is the shape the matrix needs — a policy
     * belongs to a mode, and a JSON blob cannot be a column an admin screen edits or a CHECK
     * constraint protects. The scope mechanism is untouched: `user_id NULL` is the product default,
     * a row with a user id overrides it, and an absent user row means «inherit».
     *
     * The override now works PER MODE, which is a strict widening: a user can be given typing early
     * without pinning them to today's whole set. «Inherits» stays «has no rows at all», so nothing
     * about the existing Inherit/Custom switch changes meaning.
     *
     * The seeded thresholds are the ladder as shipped ({@see ModeAdmission::shipped()}): intro at
     * the intro rung; multiple_choice from the first recognition step, with DISTANT options while
     * the pair is on the ladder and standard once it is off; assembly and choice trainers on
     * graduation; typed production and dictation after some reviews, so the plainest words do not
     * jump straight to typing.
     */
    public function up(): void
    {
        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->string('mode', 24)->nullable()->after('user_id');
            $table->boolean('enabled')->default(false)->after('mode');
            // The rotation order is part of the contract (free practice round-robins by index into
            // the enabled set, on the server and mirrored on the device), so it survives the move
            // from a JSON array as an explicit column.
            $table->smallInteger('position')->default(0)->after('enabled');
            $table->string('min_acquisition', 16)->default('graduated')->after('position');
            $table->smallInteger('min_learning_step')->nullable()->after('min_acquisition');
            /**
             * Post-graduation reviews before the trainer opens. NOT in the naряд's column list —
             * added because the ladder it specifies has rungs 4 (typing) and 5 (dictation) that
             * (acquisition, learning_step) alone cannot express, and dropping them would let a word
             * be typed the moment it graduates. Nullable, so a matrix that does not want reps
             * thresholds simply leaves it null.
             */
            $table->integer('min_reps')->nullable()->after('min_learning_step');
            $table->string('options_policy', 16)->default('standard')->after('min_reps');
        });

        // The old scope rows are replaced by per-mode rows that have no array to carry, so the
        // column has to stop being required before they are written and is dropped straight after.
        DB::statement('ALTER TABLE learning_mode_settings ALTER COLUMN modes DROP NOT NULL');
        // The old index allowed ONE row per scope, which is exactly what stops the scope being
        // exploded into one row per mode. It goes before the write, and its replacement after.
        DB::statement('DROP INDEX IF EXISTS learning_mode_settings_scope_uidx');

        $this->explodeExistingRows();

        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->dropColumn('modes');
        });

        DB::statement('ALTER TABLE learning_mode_settings ALTER COLUMN mode SET NOT NULL');
        DB::statement("ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_acq_check CHECK (min_acquisition IN ('new','learning','graduated'))");
        DB::statement("ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_options_check CHECK (options_policy IN ('distant','standard'))");
        DB::statement('ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_step_check CHECK (min_learning_step IS NULL OR min_learning_step BETWEEN 0 AND 2)');
        DB::statement('ALTER TABLE learning_mode_settings ADD CONSTRAINT learning_mode_settings_reps_check CHECK (min_reps IS NULL OR min_reps >= 0)');

        // One row per (scope, mode). Postgres treats every NULL as distinct in a unique index, so
        // the global rows would not be protected without folding NULL to a value first.
        DB::statement("CREATE UNIQUE INDEX learning_mode_settings_scope_mode_uidx ON learning_mode_settings (COALESCE(user_id::text, ''), mode)");
    }

    /**
     * Turn each scope's JSON array into one row per mode this build knows, carrying the enabled
     * flag and its position. Every mode gets a row, switched off if it was not in the array — the
     * matrix has to have somewhere to live even for a trainer nobody has turned on.
     */
    private function explodeExistingRows(): void
    {
        $shipped = ModeAdmission::shipped();
        /** @var list<object{id: string, user_id: string|null, modes: string|null}> $scopes */
        $scopes = DB::table('learning_mode_settings')->get(['id', 'user_id', 'modes'])->all();

        foreach ($scopes as $scope) {
            $decoded = json_decode((string) $scope->modes, true);
            /** @var list<string> $enabled */
            $enabled = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];

            $rows = [];
            foreach (ExerciseMode::cases() as $index => $mode) {
                $position = array_search($mode->value, $enabled, true);
                $rule = $shipped->ruleFor($mode);
                $rows[] = [
                    'id' => (string) Ulid::generate(),
                    'user_id' => $scope->user_id,
                    'mode' => $mode->value,
                    'enabled' => $position !== false,
                    // Enabled modes keep their array order; the rest are parked after them, in
                    // enum order, so switching one on lands it at the end rather than renumbering
                    // the rotation of words already partway through it.
                    'position' => $position !== false ? $position : count($enabled) + $index,
                    'min_acquisition' => $rule?->minAcquisition->value ?? 'graduated',
                    'min_learning_step' => $rule?->minLearningStep,
                    'min_reps' => $rule?->minSuccessfulReviews,
                    'options_policy' => $rule?->optionsPolicy->value ?? 'standard',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('learning_mode_settings')->where('id', $scope->id)->delete();
            DB::table('learning_mode_settings')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS learning_mode_settings_scope_mode_uidx');
        foreach (['acq', 'options', 'step', 'reps'] as $name) {
            DB::statement("ALTER TABLE learning_mode_settings DROP CONSTRAINT IF EXISTS learning_mode_settings_{$name}_check");
        }

        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->jsonb('modes')->nullable();
        });

        // Collapse the per-mode rows back into one ordered array per scope.
        /** @var array<string, list<array{mode: string, position: int}>> $byScope */
        $byScope = [];
        foreach (DB::table('learning_mode_settings')->where('enabled', true)->orderBy('position')->get() as $row) {
            $byScope[(string) $row->user_id][] = ['mode' => (string) $row->mode, 'position' => (int) $row->position];
        }

        DB::table('learning_mode_settings')->delete();
        foreach ($byScope as $userId => $modes) {
            DB::table('learning_mode_settings')->insert([
                'id' => (string) Ulid::generate(),
                'user_id' => $userId !== '' ? $userId : null,
                'modes' => json_encode(array_map(static fn (array $m): string => $m['mode'], $modes)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->dropColumn(['mode', 'enabled', 'position', 'min_acquisition', 'min_learning_step', 'min_reps', 'options_policy']);
        });
        DB::statement("CREATE UNIQUE INDEX learning_mode_settings_scope_uidx ON learning_mode_settings (COALESCE(user_id::text, ''))");
    }
};
