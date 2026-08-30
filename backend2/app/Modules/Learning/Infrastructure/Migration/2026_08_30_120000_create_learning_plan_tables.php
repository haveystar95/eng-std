<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE LEARNING PLAN — a dated mechanism, not another collection.
     *
     * A collection is a bag of words with no opinion about time. A plan has ONE goal, an event
     * DATE, and a number of days the SERVER computes from the two. That is the whole reason these
     * are separate tables rather than a `kind` column on `collections`: every field below is about
     * the deadline, and a collection has no deadline to be about.
     *
     * The relationship runs the other way round. Each introduction day OWNS an ordinary collection
     * ({@see learning_plan_days.collection_id}) — the day's phrases and substitution words are
     * plain terms in a plain collection, so every trainer, every exercise mode and the whole sync
     * contract keep working without knowing that plans exist. The plan is the mechanism; the
     * collection is where the day's material lands.
     *
     * NOT stored here, and worth saying why: the SUPPORT language. It is read from the account at
     * every point of use, exactly as `generate_collection` reads it — one learner, one native
     * language, and freezing a copy of it on the plan would create a second answer to a question
     * that already has one. (The pair is also recoverable after day 1: it is the day collection's
     * `source_lang`/`target_lang`.)
     */
    public function up(): void
    {
        Schema::create('learning_plans', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);                       // cross-module ref, no FK (like study_sessions)
            $table->string('status', 16);                      // draft|active|paused|completed|abandoned
            $table->text('title');
            // What the learner typed, verbatim, and what the model read it back as. Both, because
            // the restatement is what the plan was actually built from and «я такого не просил»
            // is only answerable with the original beside it.
            $table->text('goal_text');
            $table->text('goal_restated')->nullable();
            $table->string('target_lang', 5);
            $table->string('level', 16);                       // zero|basic|conversational|fluent
            $table->date('event_date');
            $table->integer('minutes_per_day');
            // The model's answer to P1, verbatim. Never edited in place: a re-run of the outline
            // REPLACES it, so what is here is always exactly one model answer.
            $table->jsonb('outline')->nullable();
            // The SERVER's arithmetic over that answer — PlanScheduler::compute(). Days, dates,
            // what did not fit. Written by code, never by the model.
            $table->jsonb('computed')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            // After the event: did it go the way the plan promised. Product surface, not machinery.
            $table->jsonb('event_feedback')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            "ALTER TABLE learning_plans ADD CONSTRAINT learning_plans_status_check "
            . "CHECK (status IN ('draft','active','paused','completed','abandoned'))"
        );
        DB::statement(
            "ALTER TABLE learning_plans ADD CONSTRAINT learning_plans_level_check "
            . "CHECK (level IN ('zero','basic','conversational','fluent'))"
        );
        DB::statement('ALTER TABLE learning_plans ADD CONSTRAINT learning_plans_minutes_check CHECK (minutes_per_day > 0)');
        // The hot query: «есть ли у меня активный план» on every home read, and the plan list.
        DB::statement('CREATE INDEX learning_plans_user_status_idx ON learning_plans (user_id, status)');
        // One ACTIVE plan per learner — the limit the API states, held by the database rather than
        // by a check-then-insert that two devices can both pass.
        DB::statement("CREATE UNIQUE INDEX learning_plans_one_active_uidx ON learning_plans (user_id) WHERE status = 'active'");

        Schema::create('learning_plan_days', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->integer('day_index');                      // 1-based, continuous across the plan
            $table->string('kind', 8);                         // intro | final
            // The day's own collection. NULL for the final day, which introduces nothing — and that
            // null is the schema saying so, rather than an empty collection pretending to be a day.
            $table->char('collection_id', 26)->nullable();
            $table->text('title');
            $table->text('outcome_text')->nullable();
            $table->jsonb('skills')->nullable();               // the day's abilities, from the outline
            $table->jsonb('role_brief')->nullable();           // interlocutor + opening lines + checkpoints
            // WHEN this day is meant to happen. Not in the наряд's column list and added anyway:
            // A1 spaces introduction days «через день» when there is room, and a schedule the
            // server computed and then threw away is not a schedule. The dates also live in
            // `learning_plans.computed`; this is the copy the day screen reads.
            $table->date('scheduled_on')->nullable();
            $table->string('status', 16);                      // pending|generating|ready|failed|done
            $table->integer('generation_attempts')->default(0);
            $table->text('fail_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('learning_plans')->cascadeOnDelete();
            // RESTRICT, not cascade and not null-on-delete: a day's collection is not disposable
            // while the plan is running (EnrollmentPolicy says the same thing one layer up). If a
            // hard delete ever reaches a plan collection, the right outcome is a loud failure.
            $table->foreign('collection_id')->references('id')->on('collections')->restrictOnDelete();
            $table->unique(['plan_id', 'day_index'], 'learning_plan_days_uidx');
        });

        DB::statement("ALTER TABLE learning_plan_days ADD CONSTRAINT learning_plan_days_kind_check CHECK (kind IN ('intro','final'))");
        DB::statement(
            "ALTER TABLE learning_plan_days ADD CONSTRAINT learning_plan_days_status_check "
            . "CHECK (status IN ('pending','generating','ready','failed','done'))"
        );
        // The generator's own query: «what is the next day to build for this plan».
        DB::statement('CREATE INDEX learning_plan_days_plan_status_idx ON learning_plan_days (plan_id, status)');

        /**
         * The plan's conversations — the day's test and the final rehearsal.
         *
         * Empty until CONV-1: nothing writes here yet, and the table exists now because the day
         * and the conversation are one design and splitting the schema across two наряды is how
         * `checkpoints_hit` ends up meaning something different from the checkpoints it counts.
         */
        Schema::create('plan_conversations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->char('plan_day_id', 26)->nullable();       // null for a plan-level rehearsal
            $table->string('kind', 16);                        // day | final | rehearsal
            $table->jsonb('checkpoints_hit')->nullable();
            $table->integer('hints_used')->default(0);
            // A pointer into whatever holds the transcript (practice_dialogs today), not the
            // transcript itself: one copy of a conversation, and it is not this table's.
            $table->text('transcript_ref')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('learning_plans')->cascadeOnDelete();
            $table->foreign('plan_day_id')->references('id')->on('learning_plan_days')->cascadeOnDelete();
            $table->index(['plan_id', 'kind'], 'plan_conversations_plan_idx');
        });

        DB::statement("ALTER TABLE plan_conversations ADD CONSTRAINT plan_conversations_kind_check CHECK (kind IN ('day','final','rehearsal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_conversations');
        Schema::dropIfExists('learning_plan_days');
        Schema::dropIfExists('learning_plans');
    }
};
