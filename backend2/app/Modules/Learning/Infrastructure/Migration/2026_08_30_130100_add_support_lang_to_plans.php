<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PLAN'S SUPPORT LANGUAGE, on the plan.
     *
     * PLAN-1a read it from the account at every point of use, on the reasoning that one learner has
     * one native language and a copy would be a second answer to a settled question. That is true
     * of a COLLECTION, which is a thing you look at. It is not true of a plan, which is a MECHANISM
     * that runs for days: the skeleton is written in one language, the days are written against it,
     * the keys the learner is graded on are in it, and the account is a setting a person can change
     * on Tuesday. Changing it mid-plan under the old reading did not migrate anything — it made day
     * 4 arrive in a different language from days 1–3, with the same plan claiming to be one thing.
     *
     * So the pair is a property OF THE PLAN. It is written when the plan is created, refreshed
     * while the plan is still a draft (the outline is what gets written in it, so the last word
     * before commitment is the right one), and FROZEN at `start` — after which nothing rewrites it
     * and every reader takes it from here.
     *
     * BACKFILL from `profiles.native_language`, which is exactly what the old readers resolved to,
     * so no existing plan changes meaning. A plan whose owner has no profile row falls back to
     * `ru` — the column's own default, and the same fallback the reader used.
     */
    public function up(): void
    {
        Schema::table('learning_plans', function (Blueprint $table): void {
            $table->string('support_lang', 5)->nullable()->after('target_lang');
        });

        DB::statement(
            'UPDATE learning_plans p SET support_lang = COALESCE(pr.native_language, \'ru\') '
            . 'FROM profiles pr WHERE pr.user_id = p.user_id'
        );
        // An owner with no profile row at all: the reader's fallback, written down.
        DB::statement("UPDATE learning_plans SET support_lang = 'ru' WHERE support_lang IS NULL");

        // NOT NULL only after the backfill — a plan with no support language is the state this
        // column exists to end, so the schema is allowed to say it cannot happen again.
        DB::statement('ALTER TABLE learning_plans ALTER COLUMN support_lang SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('learning_plans', function (Blueprint $table): void {
            $table->dropColumn('support_lang');
        });
    }
};
