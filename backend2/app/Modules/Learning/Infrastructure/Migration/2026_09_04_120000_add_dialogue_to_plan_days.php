<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_days.dialogue` — THE ORDER THE SCENE IS SPOKEN IN (наряд DAY-2, P2 v0.5).
     *
     * Канон `docs/plan-dialogue.md` §3: «P2 отдаёт сцену не только полками, но и цепочкой ходов —
     * порядком, в котором реплики звучат в жизни». The chain is what the dialogue screen plays; the
     * shelves stay what they were, and the ladder stays on them.
     *
     * ## A column of its own, beside `role_brief` and not inside it
     *
     * `role_brief` is the day's SNAPSHOT — written when the outline is computed, read back as the
     * brief P2 is handed, and never touched again. The chain is the ANSWER: it does not exist until
     * the day has been generated, and folding it into the snapshot would mean rewriting the brief
     * after the fact and losing the property that makes the snapshot worth having — that what the
     * model was shown is still readable after the model answered.
     *
     * ## Term ids, resolved once, at the moment both halves are in hand
     *
     * The model answers in addresses — `hear[0]`, `say[2]` — and those addresses stop meaning
     * anything the moment the cards become terms. So they are resolved in the same pass that
     * imports them ({@see \App\Modules\Generation\Application\Command\GeneratePlanDayHandler::materialize()})
     * and what lands here is `[{"turn":"role","term_id":"01J…"}, …]`: a chain that survives a
     * re-import, a re-order and a repair, because a term id is the one name a card keeps.
     *
     * NULL, never `[]`. «This day has no chain» (a plan written before v0.5) and «this day's chain
     * is empty» must not look the same in the table: the first is answered by pairing the shelves
     * ({@see \App\Modules\Learning\Domain\Service\PlanDialogueChain}) and the second would be a bug.
     *
     * No index: read by primary key, with the day.
     */
    public function up(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->jsonb('dialogue')->nullable()->after('role_brief');
        });
    }

    public function down(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->dropColumn('dialogue');
        });
    }
};
