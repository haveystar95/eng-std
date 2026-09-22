<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The seconds per card every plan made before наряд FIX-3 was reckoned by — `config('plan.pace')` as it stood until
     * then (DECISIONS пп. 309–388), written out here because the config no longer holds it: this is the snapshot those
     * plans were made with.
     */
    private const PACE_BEFORE_FIX_3 = [
        'word_intro' => 8, 'word_repeat' => 12, 'word_choose' => 10, 'word_listen' => 10, 'word_assemble' => 20, 'word_in_line' => 10,
        'phrase_intro' => 12, 'phrase_assemble' => 25, 'phrase_choose_back' => 12, 'phrase_slot' => 12, 'phrase_slot_listen' => 12,
        'phrase_repeat' => 25, 'phrase_other_slot' => 25, 'phrase_combine' => 20,
        'dialogue_partner' => 15, 'dialogue_answer' => 30, 'dialogue_ask' => 45, 'dialogue_rescue' => 15,
        'listen_dialogue' => 110, 'listen_question' => 12, 'listen_review' => 30, 'listen_predict' => 15, 'listen_pace' => 25, 'listen_number' => 15,
        'speak_answer' => 35, 'speak_echo' => 25, 'speak_retell' => 30, 'recall_scenes' => 60,
    ];

    /**
     * THE PRICE LIST OF A PLAN'S DAYS (наряд FIX-3 §2): seconds per card by kind, a snapshot of `plan.pace` taken when the
     * plan is made — what its days' «≈ N мин» and the ceiling of its «Фразы» are reckoned by. The prices were measured on
     * the phone and live in the config; a plan keeps the list it was given until `plan:repace` gives it the current one.
     *
     * Every plan already made gets the list it WAS made with ({@see PACE_BEFORE_FIX_3}), so nothing about an existing plan
     * moves at the migration: its days change their minutes when `plan:repace --all` gives it the measured list — one step
     * of the deploy, and it says what moved. Nullable, no default: the ALTER is metadata-only and the fill is one UPDATE of
     * a small table; a plan with no list reads the config's ({@see \App\Modules\Plan\Application\Service\PlanPaces}). No
     * index: read with its row.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plans ADD COLUMN pace jsonb NULL');
        DB::update('UPDATE plans SET pace = CAST(? AS jsonb) WHERE pace IS NULL', [json_encode(self::PACE_BEFORE_FIX_3, JSON_THROW_ON_ERROR)]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plans DROP COLUMN IF EXISTS pace');
    }
};
