<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * THE DAY IS DEALT FROM THE REGISTRY OF TRAINERS (наряд SESSION-1a, разд. 1–3).
     *
     * Twenty-nine card kinds replace the thirteen of the first day (`listen_pairs` reserved, never dealt), the day's
     * listening becomes a unit of its own (`unit_kind = 'day'`), and an answer keeps what it left behind — what was
     * heard, the slot's value, the judge's verdict — in `response`.
     *
     * No card of the old registry can be read any more: its kinds, payloads and units have no reader in the code this
     * migration ships with. So every dealt card goes (the owner allowed the wipe after the backup,
     * `scripts/db-backup.sh`; no live learners), and a day already opened is dealt again from its stored lesson the
     * next time it is opened. `plan_days` is untouched — its status, its dates and its numbers
     * (`cards_total` / `cards_done` / `minutes_spent`) stay what the learner earned.
     *
     * The CHECK constraints on `kind` and `unit_kind` are swapped here too: without it not one new card can be
     * inserted. `down()` cannot bring the old cards back either, so it deletes the new ones before the old checks
     * return.
     */
    public function up(): void
    {
        Log::info('plan: day cards dropped for the session registry', ['day_cards' => DB::table('day_cards')->count()]);

        DB::statement('DELETE FROM day_cards');
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_kind_check');
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_unit_kind_check');
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_kind_check CHECK (kind IN ("
            ."'word_intro','word_repeat','word_choose','word_listen','word_assemble','word_in_line',"
            ."'phrase_intro','phrase_assemble','phrase_choose_back','phrase_slot','phrase_slot_listen','phrase_repeat','phrase_other_slot','phrase_combine','phrase_own_slot',"
            ."'dialogue_partner','dialogue_answer','dialogue_ask','dialogue_rescue',"
            ."'listen_dialogue','listen_question','listen_review','listen_pairs','listen_predict','listen_pace','listen_number',"
            ."'speak_answer','speak_echo','speak_retell'))");
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_unit_kind_check CHECK (unit_kind IN ('word','phrase','exchange','day'))");
        DB::statement('ALTER TABLE day_cards ADD COLUMN response jsonb NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM day_cards');
        DB::statement('ALTER TABLE day_cards DROP COLUMN IF EXISTS response');
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_kind_check');
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_unit_kind_check');
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_kind_check CHECK (kind IN ('word_intro','word_say','word_choose','word_cloze','phrase_intro','phrase_repeat','phrase_assemble','dialogue_read','listen_question','listen_assemble','answer_choose','answer_assemble','speak'))");
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_unit_kind_check CHECK (unit_kind IN ('word','phrase','exchange'))");
    }
};
