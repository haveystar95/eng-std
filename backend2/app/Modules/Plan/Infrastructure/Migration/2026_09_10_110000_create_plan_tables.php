<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PLAN (`docs/plan-v2.md` §2): plans → scenes → days → cards, the terms of a scene, the
     * audio of a scene's partner lines, and the check counters.
     *
     * Every plan table carries `user_id` (the наряд's rule): a row can be scoped to its owner
     * without a join, and the account eraser deletes by one column.
     *
     * Indexes follow the queries: a plan by owner and status (the tab, the «one live plan» rule),
     * scenes by plan in order, days by (plan, number), cards by (day, stage, position) for the
     * card list and by (day, returns) for tomorrow's returns, terms by (scene, position), audio
     * by (scene, step, voice). JSON columns are read, never filtered on.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->text('goal_text');
            $table->string('target_lang', 5);
            $table->string('native_lang', 5);
            $table->string('level', 16);
            $table->integer('days_total');
            $table->integer('days_requested');
            $table->date('event_date')->nullable();
            $table->string('status', 16);
            $table->text('title_native')->nullable();
            $table->text('title_target')->nullable();
            $table->text('event_native')->nullable();
            $table->text('until_phrase_native')->nullable();
            $table->text('overdue_native')->nullable();
            $table->text('cover_image_prompt')->nullable();
            $table->text('learner_role_target')->nullable();
            $table->text('learner_role_native')->nullable();
            $table->text('cover_image_url')->nullable();
            $table->text('cover_image_author')->nullable();
            $table->text('cover_image_author_url')->nullable();
            $table->string('prompt_version_plan', 64)->nullable();
            $table->string('build_version', 64)->nullable();
            $table->string('model_plan', 64)->nullable();
            $table->decimal('cost_usd_plan', 10, 6)->nullable();
            $table->integer('latency_ms_plan')->nullable();
            $table->integer('attempts_plan')->nullable();
            $table->jsonb('checks_json')->nullable();
            $table->text('unclear_reason')->nullable();
            $table->text('fail_reason')->nullable();
            $table->timestampTz('build_started_at')->nullable();
            $table->char('collection_id', 26)->nullable(); // cross-module ref, no FK
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_status_check CHECK (status IN ('building','unclear','failed','ready','active','finished','overdue','deleted'))");
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_level_check CHECK (level IN ('beginner','intermediate'))");
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_days_check CHECK (days_total BETWEEN 1 AND 10 AND days_requested BETWEEN 1 AND 10)');
        DB::statement('CREATE INDEX plans_user_status_idx ON plans (user_id, status, created_at DESC)');
        DB::statement("CREATE UNIQUE INDEX plans_one_active_uidx ON plans (user_id) WHERE status = 'active'");

        Schema::create('plan_scenes', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->char('user_id', 26);
            $table->integer('order');
            $table->string('kind', 16);
            $table->integer('priority');
            $table->text('title_native');
            $table->text('title_target');
            $table->text('teaches_native');
            $table->jsonb('goals_native');
            $table->text('learner_role_target');
            $table->text('learner_role_native');
            $table->text('partner_role_target');
            $table->text('partner_role_native');
            $table->text('topic_description');
            $table->text('image_prompt');
            $table->text('image_url')->nullable();
            $table->text('image_author')->nullable();
            $table->text('image_author_url')->nullable();
            $table->jsonb('lesson_json')->nullable();
            $table->string('lesson_status', 16);
            $table->string('prompt_version_lesson', 64)->nullable();
            $table->string('build_version', 64)->nullable();
            $table->string('model_lesson', 64)->nullable();
            $table->decimal('cost_usd_lesson', 10, 6)->nullable();
            $table->integer('latency_ms_lesson')->nullable();
            $table->integer('attempts_lesson')->nullable();
            $table->jsonb('checks_json')->nullable();
            $table->text('fail_reason')->nullable();
            $table->timestampTz('build_started_at')->nullable();
            $table->timestampTz('generated_at')->nullable();
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'order'], 'plan_scenes_order_uidx');
        });
        DB::statement("ALTER TABLE plan_scenes ADD CONSTRAINT plan_scenes_kind_check CHECK (kind IN ('situation','variant'))");
        DB::statement("ALTER TABLE plan_scenes ADD CONSTRAINT plan_scenes_lesson_status_check CHECK (lesson_status IN ('pending','building','ready','failed'))");
        DB::statement('CREATE INDEX plan_scenes_plan_lesson_idx ON plan_scenes (plan_id, lesson_status)');
        DB::statement('CREATE INDEX plan_scenes_user_idx ON plan_scenes (user_id)');

        Schema::create('plan_days', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->char('user_id', 26);
            $table->integer('number');
            $table->string('type', 16);
            $table->char('scene_id', 26)->nullable();
            $table->string('status', 16);
            $table->date('opens_on')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->integer('cards_total')->default(0);
            $table->integer('cards_done')->default(0);
            $table->integer('minutes_spent')->default(0);
            $table->decimal('first_try_share', 4, 2)->nullable();
            $table->string('hardest_unit_kind', 16)->nullable();
            $table->string('hardest_unit_ref', 16)->nullable();
            $table->text('hardest_unit_text')->nullable();
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('scene_id')->references('id')->on('plan_scenes')->nullOnDelete();
            $table->unique(['plan_id', 'number'], 'plan_days_number_uidx');
        });
        DB::statement("ALTER TABLE plan_days ADD CONSTRAINT plan_days_type_check CHECK (type IN ('scene','review','rehearsal'))");
        DB::statement("ALTER TABLE plan_days ADD CONSTRAINT plan_days_status_check CHECK (status IN ('locked','open','in_progress','closed'))");
        DB::statement('CREATE INDEX plan_days_scene_idx ON plan_days (scene_id)');
        DB::statement('CREATE INDEX plan_days_user_status_idx ON plan_days (user_id, status)');

        Schema::create('day_cards', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('day_id', 26);
            $table->char('user_id', 26);
            $table->string('stage', 16);
            $table->integer('position');
            $table->string('kind', 24);
            $table->jsonb('payload');
            $table->string('source', 16);
            $table->char('source_day_id', 26)->nullable();
            $table->string('unit_kind', 16);
            $table->string('unit_ref', 16);
            $table->char('retry_of', 26)->nullable();
            $table->string('result', 16)->nullable();
            $table->integer('attempts')->default(0);
            $table->timestampTz('answered_at')->nullable();
            $table->boolean('returns')->default(false);
            $table->timestampsTz();

            $table->foreign('day_id')->references('id')->on('plan_days')->cascadeOnDelete();
            $table->foreign('source_day_id')->references('id')->on('plan_days')->nullOnDelete();
            $table->unique(['day_id', 'stage', 'position'], 'day_cards_position_uidx');
        });
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak'))");
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_kind_check CHECK (kind IN ('word_intro','word_say','word_choose','word_cloze','phrase_intro','phrase_repeat','phrase_assemble','dialogue_read','listen_question','listen_assemble','answer_choose','answer_assemble','speak'))");
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_source_check CHECK (source IN ('today','returned'))");
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_result_check CHECK (result IS NULL OR result IN ('passed','hinted','failed','skipped'))");
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_unit_kind_check CHECK (unit_kind IN ('word','phrase','exchange'))");
        // Tomorrow's returns: the failed-twice cards of one day. Partial, because they are the few.
        DB::statement('CREATE INDEX day_cards_returns_idx ON day_cards (day_id) WHERE returns = true');
        DB::statement('CREATE INDEX day_cards_source_day_idx ON day_cards (source_day_id) WHERE source_day_id IS NOT NULL');
        DB::statement('CREATE INDEX day_cards_user_idx ON day_cards (user_id)');

        Schema::create('plan_terms', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('scene_id', 26);
            $table->char('user_id', 26);
            $table->string('kind', 16);
            $table->string('ref', 16);
            $table->integer('position');
            $table->text('text_target');
            $table->text('text_native');
            $table->text('pronunciation_native')->nullable();
            $table->text('definition_target')->nullable();
            $table->text('example_target')->nullable();
            $table->text('example_native')->nullable();
            $table->text('speaking_key')->nullable();
            $table->jsonb('simplified_variants')->nullable();
            $table->text('image_prompt')->nullable();
            $table->text('image_url')->nullable();
            $table->text('image_author')->nullable();
            $table->text('image_author_url')->nullable();
            $table->timestampsTz();

            $table->foreign('scene_id')->references('id')->on('plan_scenes')->cascadeOnDelete();
            $table->unique(['scene_id', 'ref'], 'plan_terms_ref_uidx');
        });
        DB::statement("ALTER TABLE plan_terms ADD CONSTRAINT plan_terms_kind_check CHECK (kind IN ('word','chunk','phrase'))");
        DB::statement('CREATE INDEX plan_terms_scene_position_idx ON plan_terms (scene_id, position)');
        DB::statement('CREATE INDEX plan_terms_user_idx ON plan_terms (user_id)');

        Schema::create('plan_line_audios', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('scene_id', 26);
            $table->char('user_id', 26);
            $table->integer('step');
            $table->string('voice_key', 96);
            $table->string('format', 8);
            $table->text('path');
            $table->integer('bytes');
            $table->integer('duration_ms')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->foreign('scene_id')->references('id')->on('plan_scenes')->cascadeOnDelete();
            // Idempotency by schema: the same line with the same voice is never bought twice.
            $table->unique(['scene_id', 'step', 'voice_key'], 'plan_line_audios_uidx');
        });
        DB::statement('CREATE INDEX plan_line_audios_user_idx ON plan_line_audios (user_id)');

        Schema::create('plan_check_counters', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('prompt_version', 64);
            $table->string('check_name', 48);
            $table->string('action', 16);
            $table->integer('hits')->default(0);
            $table->timestampTz('updated_at')->nullable();

            $table->unique(['prompt_version', 'check_name', 'action'], 'plan_check_counters_uidx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_check_counters');
        Schema::dropIfExists('plan_line_audios');
        Schema::dropIfExists('plan_terms');
        Schema::dropIfExists('day_cards');
        Schema::dropIfExists('plan_days');
        Schema::dropIfExists('plan_scenes');
        Schema::dropIfExists('plans');
    }
};
