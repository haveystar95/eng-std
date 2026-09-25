<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * EVERY DAY HAS SIX STAGES (наряд ACC-1 §3; решение архитектора 22.09 по BACK-TAILS-2 §11).
     *
     * `plan_days.has_conversation` remembered, day by day, whether the day was dealt WITH the talk — the column of the
     * talk's rollout (CONV-1) — and it goes together with the switch `PLAN_CONVERSATION_ENABLED`. A day dealt WITHOUT the
     * talk must not be broken by that: read by the code after this migration it would be a day of six stages whose
     * sixth nobody can walk — no talk was dealt to it — and it could never be closed. So every such day, in progress or
     * closed, first gets its sixth stage SKIPPED: a passage of the talk with no talk (`conversation_id` null,
     * `passed_at` — the moment the day was dealt). Skipped is behind the day and is not passed: no talk may be started
     * on it (422 `plan_conversation_not_in_day`), it adds no minutes, sends nothing back tomorrow and draws no row.
     * Then the column is dropped. A day not dealt yet carries nothing: it is dealt with the talk.
     *
     * On the stand before the наряд (25.09, read only): 4 days in progress — all of deleted plans — and 6 closed days,
     * every one of them dealt before 21.09. The days are named in the log line and in the order's report.
     *
     * Reversible: `down()` puts the column back — false for a day whose talk is skipped, true for every other dealt day,
     * false (the default) for a day not dealt — and takes the skipped passages out: the code before this наряд never
     * wrote one.
     */
    public function up(): void
    {
        $days = DB::table('plan_days')
            ->whereNotNull('opened_at')
            ->where('has_conversation', false)
            ->orderBy('opened_at')
            ->get(['id', 'plan_id', 'number', 'status', 'opened_at']);

        $now = now();
        $skipped = [];
        foreach ($days as $day) {
            $written = DB::table('plan_stage_passages')->insertOrIgnore([
                'id' => Ulid::generate(),
                'plan_id' => $day->plan_id,
                'day_id' => $day->id,
                'stage' => 'conversation',
                'conversation_id' => null,
                'passed_at' => $day->opened_at,
                'created_at' => $now,
            ]);
            if ($written > 0) {
                $skipped[] = "{$day->plan_id}:{$day->number}:{$day->status}";
            }
        }

        DB::statement('ALTER TABLE plan_days DROP COLUMN has_conversation');

        Log::info('plan: the talk of the days dealt without it skipped, plan_days.has_conversation dropped', [
            'days_skipped' => count($skipped),
            'days' => $skipped,
        ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_days ADD COLUMN has_conversation boolean NOT NULL DEFAULT false');
        DB::statement(<<<'SQL'
            UPDATE plan_days SET has_conversation = true
            WHERE opened_at IS NOT NULL
              AND id NOT IN (SELECT day_id FROM plan_stage_passages WHERE stage = 'conversation' AND conversation_id IS NULL)
            SQL);
        DB::statement("DELETE FROM plan_stage_passages WHERE stage = 'conversation' AND conversation_id IS NULL");
    }
};
