<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE ABILITY BECOMES A ROW, AND THE DAY BECOMES A DERIVATIVE.
     *
     * Until v0.2 the model answered in DAYS: P1 was told how many days it had, cut the plan into
     * that many, and priced each one. The server then «computed» `need` as the sum of those prices
     * — the same number it had handed over one call earlier. The circle was invisible and it cost
     * the owner a plan: «иду к врачу через 30 дней» produced twenty-nine introduction days, because
     * every gate that was supposed to notice («не влезает», the cap of fourteen) was comparing the
     * server's own arithmetic with itself.
     *
     * v0.2 cuts it: P1 answers in SCENES and SKILLS, each skill priced by the model in `est_terms`,
     * and the days are computed here, from those prices and the calendar
     * ({@see \App\Modules\Learning\Domain\Service\PlanScheduler}). So the unit that is STORED has to
     * be the skill, not the day — a day is now what the scheduler decides today and may decide
     * differently tomorrow (A7 re-runs it when the learner moves the date or falls behind), while
     * the skills are what the learner was promised and do not move.
     *
     * ## `learning_plan_days` stays, and stays derived
     *
     * Its `skills` and `role_brief` columns keep the scheduler's SNAPSHOT of what landed on each
     * day, so every existing reader (the day screen, the generator's brief, the admin projection)
     * keeps reading one row and does not learn to join. The snapshot is rewritten whenever the
     * schedule is recomputed; this table is what it is rewritten FROM.
     *
     * ## Why `role` sits on the skill row
     *
     * It belongs to the scene, and the scene is not a table. Giving it one would buy a join and a
     * second id for something that is written once, read whole, and never addressed on its own —
     * the role is only ever wanted together with the skills it is the interlocutor for. So it is
     * repeated on every row of its scene, identically, and `scene_index` is what makes that
     * repetition legible rather than accidental.
     *
     * ## `position` and not `order`
     *
     * `order` is a reserved word in Postgres and a column named it has to be quoted in every
     * statement that touches it, forever, including the ones written in a hurry. The name is the
     * only thing being given up.
     */
    public function up(): void
    {
        Schema::create('plan_skills', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);

            // The scene this ability lives in: its index in P1's answer, its title, and the person
            // on the other side of it. Repeated across the scene's rows — see the docblock.
            $table->integer('scene_index');
            $table->text('scene_title');
            $table->jsonb('role')->nullable();      // {name, opening_lines[], if_silent} — null = no interlocutor

            // Where this ability sits INSIDE its scene, and what it promises.
            $table->integer('skill_index');
            $table->text('outcome');
            $table->text('checkpoint');
            $table->integer('est_terms');
            $table->jsonb('topics');                // the AREAS its substitution words come from

            /**
             * P1's own order across the whole plan, 0-based — and the plan's PRIORITY.
             *
             * The prompt is explicit that the order is a dependency order and that the server cuts
             * from the tail when the deadline is short. So this column is not decoration: it is the
             * sequence {@see \App\Modules\Learning\Domain\Service\PlanScheduler::fitInOrder()}
             * truncates, and re-sorting it by anything else would change which abilities the
             * learner loses.
             */
            $table->integer('position');

            // The scheduler's answer, and the two fields that are allowed to change over a plan's
            // life. `day_index` null = not scheduled onto any day yet (or dropped).
            $table->integer('day_index')->nullable();
            $table->boolean('dropped')->default(false);

            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('learning_plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'position'], 'plan_skills_position_uidx');
        });

        // An ability that costs nothing would make the scheduler believe a plan is free; the prompt
        // asks for 3–8 and the validator refuses anything else, so this is the floor under both.
        DB::statement('ALTER TABLE plan_skills ADD CONSTRAINT plan_skills_est_terms_check CHECK (est_terms > 0)');

        // The scheduler's own read: «what landed on day N of this plan», and «what did not land».
        DB::statement('CREATE INDEX plan_skills_plan_day_idx ON plan_skills (plan_id, day_index)');

        Schema::table('learning_plans', function (Blueprint $table): void {
            /**
             * WHY this plan stopped, when it stopped for a reason the learner did not choose.
             *
             * Null on every plan the learner abandoned themselves — «я передумал» needs no column.
             * It exists because the migration below abandons plans on the owner's behalf, and a
             * plan that says «abandoned» with nothing beside it is a plan the owner will re-open
             * next month and not know why it died.
             */
            $table->string('abandon_reason', 64)->nullable()->after('status');
        });

        /**
         * EVERY PLAN BUILT ON v0.1 IS RETIRED HERE.
         *
         * Their `outline` JSON is the old shape — days with a shared `term_budget`, checkpoints
         * hanging off the role in a list parallel to `outcome` — and nothing downstream can read it
         * any more: the scheduler now wants priced skills and the day brief now wants scenes. A
         * plan left `active` in that state would fail on the next read rather than at a moment
         * anyone chose, which is the worse of the two failures.
         *
         * Only the RUNNABLE ones. A `completed` plan is history and re-labelling it would rewrite
         * what actually happened; a draft that never got an outline has nothing of v0.1 in it and
         * is built on v0.2 the moment it is opened. There are no other users — the owner re-creates
         * the plans that matter, which is what the наряд that brought v0.2 says out loud.
         */
        DB::statement(
            "UPDATE learning_plans SET abandon_reason = 'prompt_v0_2', status = 'abandoned', updated_at = now() "
            . "WHERE outline IS NOT NULL AND status IN ('draft','active','paused')"
        );
    }

    public function down(): void
    {
        /**
         * The retired plans come back PAUSED, and that is a deliberate loss.
         *
         * The status they had before is not recoverable — this column never carried it — and paused
         * is the one resumable state that cannot collide with `learning_plans_one_active_uidx` if
         * the owner has started a new plan since. The rollback check runs on a disposable database
         * where no such row exists, so this branch is for the person who runs it by hand.
         */
        DB::statement(
            "UPDATE learning_plans SET status = 'paused', updated_at = now() "
            . "WHERE abandon_reason = 'prompt_v0_2' AND status = 'abandoned'"
        );

        Schema::table('learning_plans', function (Blueprint $table): void {
            $table->dropColumn('abandon_reason');
        });

        Schema::dropIfExists('plan_skills');
    }
};
