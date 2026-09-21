<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «ПОВТОРЕНИЕ» — THE REVIEW DAY'S OWN STAGE (наряд BACK-TAILS-2 §3).
     *
     * A review day dealt its cards under `speak`, and the client drew «Говорю сам» over a day that has no such stage. The
     * stage is now `repetition` (`Stage::Repetition`, `CardKind::stage(DayType::Review)`), so:
     *
     * - `day_cards.stage` and `plan_stage_passages.stage` learn the value;
     * - the cards review days were ALREADY dealt with move from `speak` to `repetition` — only on days of type `review`:
     *   a scene day's «Говорю сам» is `speak` and stays so. Nothing else of a card changes.
     *
     * Reversible: back, every `repetition` card is a review day's `speak` card again, and the constraints forget the value.
     * The journal of stages has no `repetition` row to move — only the talk writes it so far.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_stage_check');
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak','recall','repetition'))");
        DB::statement('ALTER TABLE plan_stage_passages DROP CONSTRAINT IF EXISTS plan_stage_passages_stage_check');
        DB::statement("ALTER TABLE plan_stage_passages ADD CONSTRAINT plan_stage_passages_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak','recall','repetition','conversation'))");

        DB::statement("UPDATE day_cards SET stage = 'repetition' WHERE stage = 'speak' AND day_id IN (SELECT id FROM plan_days WHERE type = 'review')");
    }

    public function down(): void
    {
        DB::statement("UPDATE day_cards SET stage = 'speak' WHERE stage = 'repetition'");

        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_stage_check');
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak','recall'))");
        DB::statement('ALTER TABLE plan_stage_passages DROP CONSTRAINT IF EXISTS plan_stage_passages_stage_check');
        DB::statement("ALTER TABLE plan_stage_passages ADD CONSTRAINT plan_stage_passages_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak','recall','conversation'))");
    }
};
