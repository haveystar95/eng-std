<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE FIRST LEARNING PLAN IS GONE — tables, columns and the trainers that existed only for it.
     *
     * The plan was rebuilt as its own module (`app/Modules/Plan`, `docs/plan-v2.md`), and nothing
     * of the old mechanism is reused: not the day collections, not the A/B/C ladder, not the
     * situational trainers, not the per-level knobs in `learning_mode_settings`. Its migrations were
     * deleted rather than reversed one by one (history is in git); this one migration takes the
     * dev database from wherever they left it to the state a fresh database is in. Every step is
     * guarded, so it is a no-op on a database that never had the old plan.
     *
     * What it touches OUTSIDE the plan's own tables, and why:
     *
     *  - `collections` — the old plan wrote one private collection per day. They are SOFT-deleted
     *    (tombstones for the phone's mirror), never hard-deleted, so the learner's other folders and
     *    the global terms the days introduced are untouched.
     *  - `reviews` — the three situational trainers wrote rows that no longer name a known mode.
     *    They are deleted (the only rows of a dead trainer) and the CHECK is narrowed back.
     *  - `learning_mode_settings` — the `scope` / `level` / `knobs` dimension existed only for
     *    the plan's per-level rows; back to one row per (user, mode).
     *  - `user_term_progress.enrollment_sources` — `plan:<id>` reasons are stripped; the enrolment
     *    itself stays (a plan ending never un-enrolled a word).
     *
     * No `down()`: the dropped tables held generated lessons of a mechanism that no longer exists,
     * and the наряд explicitly allows this migration to be one-way.
     */
    private const OLD_TABLES = [
        'learning_plan_day_stage_passages',
        'learning_plan_scene_runs',
        'learning_plan_term_stages',
        'plan_skills',
        'plan_conversations',
        'learning_plan_days',
        'learning_plans',
    ];

    private const MODES = [
        'multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble',
        'dictation', 'pick_correct', 'speaking', 'description_match',
    ];

    private const SITUATIONAL = ['situational_hear', 'situational_say', 'situational_ask'];

    public function up(): void
    {
        $this->tombstoneDayCollections();
        $this->dropOldTables();
        $this->flattenModeSettings();
        $this->narrowReviewModes();
        $this->stripPlanEnrollmentSources();
    }

    public function down(): void
    {
        // One-way by design — see the class docblock.
    }

    private function tombstoneDayCollections(): void
    {
        if (! Schema::hasTable('learning_plan_days') || ! Schema::hasColumn('learning_plan_days', 'collection_id')) {
            return;
        }

        DB::table('collections')
            ->whereNull('deleted_at')
            ->whereIn('id', DB::table('learning_plan_days')->whereNotNull('collection_id')->select('collection_id'))
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    private function dropOldTables(): void
    {
        foreach (self::OLD_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function flattenModeSettings(): void
    {
        if (! Schema::hasColumn('learning_mode_settings', 'scope')) {
            return;
        }

        DB::table('learning_mode_settings')->where('scope', 'plan')->delete();

        foreach (['scope', 'level', 'plan_level', 'plan_global'] as $name) {
            DB::statement("ALTER TABLE learning_mode_settings DROP CONSTRAINT IF EXISTS learning_mode_settings_{$name}_check");
        }
        DB::statement('DROP INDEX IF EXISTS learning_mode_settings_scope_mode_uidx');

        Schema::table('learning_mode_settings', function (Blueprint $table): void {
            $table->dropColumn(['scope', 'level', 'knobs']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX learning_mode_settings_scope_mode_uidx ON learning_mode_settings '
            . "(COALESCE(user_id::text, ''), mode)"
        );
    }

    private function narrowReviewModes(): void
    {
        DB::table('reviews')->whereIn('exercise_mode', self::SITUATIONAL)->delete();

        $list = "'" . implode("','", self::MODES) . "'";
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_exercise_mode_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_exercise_mode_check CHECK (exercise_mode IN ({$list}))");
    }

    private function stripPlanEnrollmentSources(): void
    {
        DB::statement(<<<'SQL'
            UPDATE user_term_progress
            SET enrollment_sources = COALESCE(
                (SELECT jsonb_agg(e) FROM jsonb_array_elements(enrollment_sources) AS e WHERE e::text NOT LIKE '"plan:%'),
                '[]'::jsonb
            )
            WHERE enrollment_sources::text LIKE '%"plan:%'
        SQL);
    }
};
