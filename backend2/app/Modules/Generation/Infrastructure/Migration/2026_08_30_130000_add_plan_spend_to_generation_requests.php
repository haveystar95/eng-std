<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PLAN GETS A LEDGER ROW, in the ledger that already exists.
     *
     * PLAN-1a shipped with the plan's spend recorded in exactly one place — the outbound request
     * log — and the live run proved what that is worth: the `purpose = 'plan'` CHECK had not been
     * applied to the dev database, `LogOutboundHttp` swallowed the refusal (deliberately —
     * observability must never break the call it observes), and three paid `gpt-5.4` calls
     * happened with no record anywhere. They are not recoverable: nothing else stored their tokens.
     *
     * A log is a log. A LEDGER is a row the work itself writes and cannot proceed without, and
     * `generation_requests` has been that for collections since day one — tokens, cost, model,
     * status, the user it belongs to. Plans go in the same table rather than a new one, because a
     * second spend table is how two cost screens start disagreeing about what the month cost.
     *
     * ## `purpose`, and why every existing row is `generation`
     *
     * The column exists to keep two products apart in one ledger. Everything written before today
     * was a collection generation, so the default is not a guess — it is the fact, and it is why
     * no backfill statement is needed.
     *
     * ## Three readers had to be told about it, and each for a different reason
     *
     *  - the daily QUOTA counts rows in this table. A plan must not eat the learner's
     *    «создать коллекцию» allowance — they are different acts with different limits.
     *  - the PROMPT CACHE looks up a finished row by normalized prompt and hands back its
     *    collection. A plan row has no collection and its prompt is a goal, not a topic; matching
     *    one would serve a plan day's collection to somebody asking for a topic.
     *  - the admin GENERATION LIST is a screen about collections. A plan row there reads as a
     *    generation that lost its collection.
     *
     * The cost screen is the one reader that must NOT filter: money is money, and the total has to
     * keep including it. It gains a `plan` line instead.
     *
     * ## No foreign key on `plan_id`
     *
     * Cross-module reference, same rule as `study_sessions.collection_id`: `generation_requests`
     * is Generation's table and `learning_plans` is Learning's. A FK would also decide, silently,
     * whether spend history survives its plan — and a ledger that a delete can erase is not one.
     */
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->string('purpose', 16)->default('generation')->after('status');
            $table->char('plan_id', 26)->nullable()->after('collection_id');
        });

        DB::statement('ALTER TABLE generation_requests ALTER COLUMN purpose SET NOT NULL');
        DB::statement(
            'ALTER TABLE generation_requests ADD CONSTRAINT generation_requests_purpose_check '
            . "CHECK (purpose IN ('generation','plan'))"
        );

        // «Что стоил этот план» — the query the plan's own cost line is made of.
        DB::statement("CREATE INDEX generation_requests_plan_idx ON generation_requests (plan_id) WHERE plan_id IS NOT NULL");
        // «Что стоило это за месяц, по продуктам» — the cost screen's two sums.
        DB::statement('CREATE INDEX generation_requests_purpose_idx ON generation_requests (purpose, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS generation_requests_purpose_idx');
        DB::statement('DROP INDEX IF EXISTS generation_requests_plan_idx');
        DB::statement('ALTER TABLE generation_requests DROP CONSTRAINT IF EXISTS generation_requests_purpose_check');

        // Plan rows would be indistinguishable from collection generations once the column is
        // gone, and a collection generation with no collection is a broken row on three screens.
        // They are deleted rather than left to masquerade — the same reasoning the purpose
        // whitelist migrations use when they relabel rather than drop.
        DB::table('generation_requests')->where('purpose', 'plan')->delete();

        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn(['purpose', 'plan_id']);
        });
    }
};
