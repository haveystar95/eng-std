<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE TALK KNOWS ITS SCENES AND WHAT IT REFUSED (наряд FIX-4 §§2–4, 6; решение владельца 24.09).
     *
     * `conversation_turns`:
     * - `scene_id` — the scene a line was said in (the talk's current one for a move; the role's scene for a line of the
     *   role). Null on the lines written before: which scene an old line stood in the journal did not say.
     * - `scene_event` — in a talk over several scenes, `start` on the new role's greeting and `end` on a scene's goodbye;
     *   null on every other line, and on every line of a day's talk.
     * - `phrases_almost` — the constructions a move said ALMOST, in the form `phrases_used` keeps the ones it said: ids
     *   `<scene>:<ref>`. `[]` on everything written before.
     *
     * `conversation_rejections` — the journal of what the server refused of the role's answers, one table for both kinds:
     * `rejected_answer` (a guard asked the model once more: the attempt, its reason, its call) and `dropped_opening` (the
     * door the role named is no door of its scene). Append-only: rows are inserted with the move and never changed.
     * Read with its talk: the index `(conversation_id, turn_index)`.
     *
     * The journal of turns is append-only too, so the ALTERs are metadata-only: nullable columns, and one with a constant
     * default. Down drops exactly what this adds.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE conversation_turns ADD COLUMN scene_id char(26) NULL');
        DB::statement('ALTER TABLE conversation_turns ADD COLUMN scene_event varchar(8) NULL');
        DB::statement("ALTER TABLE conversation_turns ADD CONSTRAINT conversation_turns_scene_event_check CHECK (scene_event IS NULL OR scene_event IN ('start', 'end'))");
        DB::statement("ALTER TABLE conversation_turns ADD COLUMN phrases_almost jsonb NOT NULL DEFAULT '[]'::jsonb");

        DB::statement(<<<'SQL'
            CREATE TABLE conversation_rejections (
                id char(26) PRIMARY KEY,
                conversation_id char(26) NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
                turn_index integer NOT NULL,
                attempt smallint NOT NULL,
                kind varchar(24) NOT NULL,
                reason varchar(32) NOT NULL,
                model_call_id char(26) NULL,
                detail jsonb NOT NULL DEFAULT '{}'::jsonb,
                created_at timestamp(0) with time zone NOT NULL,
                CONSTRAINT conversation_rejections_kind_check CHECK (kind IN ('rejected_answer', 'dropped_opening')),
                CONSTRAINT conversation_rejections_attempt_check CHECK (attempt >= 1)
            )
            SQL);
        DB::statement('CREATE INDEX conversation_rejections_turn_idx ON conversation_rejections (conversation_id, turn_index)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS conversation_rejections');
        DB::statement('ALTER TABLE conversation_turns DROP COLUMN IF EXISTS phrases_almost');
        DB::statement('ALTER TABLE conversation_turns DROP CONSTRAINT IF EXISTS conversation_turns_scene_event_check');
        DB::statement('ALTER TABLE conversation_turns DROP COLUMN IF EXISTS scene_event');
        DB::statement('ALTER TABLE conversation_turns DROP COLUMN IF EXISTS scene_id');
    }
};
