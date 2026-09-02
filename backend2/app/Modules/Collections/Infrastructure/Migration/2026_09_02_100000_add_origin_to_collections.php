<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WHERE A FOLDER CAME FROM — and the one value it has today is `plan`.
     *
     * A plan day owns an ordinary collection, and that is deliberate: it is what lets the whole
     * session machinery deal a plan card without knowing plans exist. The cost was that it is
     * ordinary in every list too. `type = custom`, `visibility = private`, `owner_id = <the
     * learner>` and nothing else — so «Мои коллекции» listed «Ответить на вопр…» and «Открыть приём
     * д…» beside «У врача и в аптеке» (Д-34), and the home screen's word-challenge drew its wrong
     * answers out of the plan's own replies, before AND after the plan was archived (Д-35).
     *
     * The link plan → collection already exists (`learning_plan_days.collection_id`); what did not
     * exist is the link the other way, and Collections has no business reading a Learning table to
     * find one. So the fact is written where it is read: one nullable tag, set at the moment the day
     * is materialised.
     *
     * NOT a `type`. The three types (`system` / `shared` / `custom`) say who may see the folder and
     * they are checked everywhere; a fourth would be a new access rule to get wrong in ten places. A
     * plan day IS a private custom folder — the tag says only where it came from, and every list
     * decides for itself whether that matters.
     *
     * Existing plan days are backfilled off `learning_plan_days`. Written in SQL rather than through
     * the modules for the reason every backfill is: this is a one-time correction of history, not a
     * behaviour, and it must not depend on the two modules that are about to start disagreeing about
     * whose job it is.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->string('origin', 16)->nullable()->after('source');
        });

        // The index is partial and tiny: every read of it asks «which of these are NOT plan days»,
        // and the plan days are the few.
        DB::statement('CREATE INDEX collections_origin_idx ON collections (origin) WHERE origin IS NOT NULL');

        DB::statement(
            "UPDATE collections SET origin = 'plan' WHERE id IN "
            . '(SELECT collection_id FROM learning_plan_days WHERE collection_id IS NOT NULL)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS collections_origin_idx');

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });
    }
};
