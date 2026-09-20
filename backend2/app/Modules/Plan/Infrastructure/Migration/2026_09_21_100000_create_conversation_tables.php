<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE TALK WITH THE AGENT (наряд CONV-1): the sixth stage of a day, and the two tables it
     * writes — the talk and its append-only journal of lines.
     *
     * Three things happen here besides the new tables:
     *
     * - `day_cards.stage` learns `recall` — the rehearsal's «Вспомнить» (кадр 37-3). `conversation`
     *   is NOT added: the talk has no cards at all, which is the whole point of its own journal;
     * - `day_cards.kind` learns `recall_scenes`, the walkthrough that shows the plan's own lines;
     * - `plan_days.has_conversation` remembers, per day, whether the day was dealt WITH the talk.
     *   A day already open keeps the composition it was dealt with (the rule FIX-2 §7 wrote for the
     *   cards), so an unfinished day of the old shape still closes on five stages while every day
     *   opened from here on needs six. Nothing is re-dealt.
     *
     * Indexes follow the three queries there are: the one open talk of a day (the partial unique
     * index is also the rule — one open talk per day, «Ещё раз» closes the old one), the latest
     * talk of a day (the day window and the summary), and a talk's lines in order.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('plan_id', 26);
            $table->char('day_id', 26);
            $table->integer('day_number');
            $table->string('type', 16);                 // day | rehearsal | review
            $table->string('state', 16);                // agent_turn | your_turn | ended
            $table->jsonb('scene_ids');                 // the checkpoints, in the order they are walked
            $table->jsonb('checkpoints_done');
            $table->integer('turn_limit');
            $table->boolean('hints_enabled')->default(true);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->string('ended_reason', 16)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('day_id')->references('id')->on('plan_days')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_type_check CHECK (type IN ('day','rehearsal','review'))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_state_check CHECK (state IN ('agent_turn','your_turn','ended'))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_ended_reason_check CHECK (ended_reason IS NULL OR ended_reason IN ('natural','limit','declined','replayed'))");
        // One open talk per day — the rule, not a convention: «Ещё раз» closes the old one as `replayed` first.
        DB::statement("CREATE UNIQUE INDEX conversations_day_open_uidx ON conversations (day_id) WHERE state <> 'ended'");
        DB::statement('CREATE INDEX conversations_day_started_idx ON conversations (day_id, started_at DESC)');
        DB::statement('CREATE INDEX conversations_user_started_idx ON conversations (user_id, started_at DESC)');

        Schema::create('conversation_turns', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('conversation_id', 26);
            $table->char('user_id', 26);
            $table->integer('turn_index');
            $table->string('kind', 16);                 // agent | said | rescue | skip
            $table->string('speaker', 16);              // partner | learner
            $table->text('text_target')->nullable();
            $table->text('text_native')->nullable();

            // The voice of the line, bought for this turn alone — the file lands on the day's own disk.
            $table->text('audio_path')->nullable();
            $table->string('audio_format', 8)->nullable();
            $table->integer('audio_duration_ms')->nullable();
            $table->string('audio_voice_key', 100)->nullable();
            $table->integer('audio_characters')->nullable();
            $table->integer('audio_credits')->nullable();
            $table->decimal('audio_cost_usd', 10, 6)->nullable();
            $table->string('audio_request_id', 120)->nullable();

            // What the role judged about the learner's move this line answers.
            $table->boolean('understood')->nullable();
            $table->jsonb('phrases_used');              // scene-qualified ids, matched by the server's own rule
            $table->boolean('off_topic')->nullable();
            $table->char('checkpoint_done', 26)->nullable();
            $table->text('hint_native')->nullable();

            // What the turn cost and how long the learner waited for it (наряд CONV-1, п. 5–6).
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 64)->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('model_cost_usd', 10, 6)->default(0);
            $table->decimal('speech_cost_usd', 10, 6)->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->integer('model_latency_ms')->nullable();
            $table->integer('speech_latency_ms')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->unique(['conversation_id', 'turn_index'], 'conversation_turns_index_uidx');
        });
        DB::statement("ALTER TABLE conversation_turns ADD CONSTRAINT conversation_turns_kind_check CHECK (kind IN ('agent','said','rescue','skip'))");
        DB::statement("ALTER TABLE conversation_turns ADD CONSTRAINT conversation_turns_speaker_check CHECK (speaker IN ('partner','learner'))");
        DB::statement('CREATE INDEX conversation_turns_user_idx ON conversation_turns (user_id)');

        // The rehearsal's «Вспомнить» — a stage of cards; the talk is not one.
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_stage_check');
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak','recall'))");
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_kind_check');
        DB::statement('ALTER TABLE day_cards ADD CONSTRAINT day_cards_kind_check CHECK (kind IN ('
            ."'word_intro','word_repeat','word_choose','word_listen','word_assemble','word_in_line',"
            ."'phrase_intro','phrase_assemble','phrase_choose_back','phrase_slot','phrase_slot_listen','phrase_repeat','phrase_other_slot','phrase_combine',"
            ."'dialogue_partner','dialogue_answer','dialogue_ask','dialogue_rescue',"
            ."'listen_dialogue','listen_question','listen_review','listen_pairs','listen_predict','listen_pace','listen_number',"
            ."'speak_answer','speak_echo','speak_retell',"
            ."'recall_scenes'))");

        // Which composition a day was dealt with. False for every day that already exists: its cards
        // are the five stages, and a day the learner is in the middle of is not re-dealt under them.
        DB::statement('ALTER TABLE plan_days ADD COLUMN has_conversation boolean NOT NULL DEFAULT false');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_days DROP COLUMN IF EXISTS has_conversation');
        DB::statement("DELETE FROM day_cards WHERE stage = 'recall' OR kind = 'recall_scenes'");
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_stage_check');
        DB::statement("ALTER TABLE day_cards ADD CONSTRAINT day_cards_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak'))");
        DB::statement('ALTER TABLE day_cards DROP CONSTRAINT IF EXISTS day_cards_kind_check');
        DB::statement('ALTER TABLE day_cards ADD CONSTRAINT day_cards_kind_check CHECK (kind IN ('
            ."'word_intro','word_repeat','word_choose','word_listen','word_assemble','word_in_line',"
            ."'phrase_intro','phrase_assemble','phrase_choose_back','phrase_slot','phrase_slot_listen','phrase_repeat','phrase_other_slot','phrase_combine','phrase_own_slot',"
            ."'dialogue_partner','dialogue_answer','dialogue_ask','dialogue_rescue',"
            ."'listen_dialogue','listen_question','listen_review','listen_pairs','listen_predict','listen_pace','listen_number',"
            ."'speak_answer','speak_echo','speak_retell'))");

        Schema::dropIfExists('conversation_turns');
        Schema::dropIfExists('conversations');
    }
};
