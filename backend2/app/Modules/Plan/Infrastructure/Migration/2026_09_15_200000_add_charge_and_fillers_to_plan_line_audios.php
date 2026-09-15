<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * WHAT A LINE COST, AND THE FILLERS (наряд TTS-2).
     *
     *  - `characters` — the characters of the line's text; `cost_usd` beside it is their price at the model's rate.
     *  - `credits` — what the voice vendor debited from the account for the line (its `character-cost` header; a
     *    dialogue's credits shared out among its lines by length). Not the characters: how many credits a character takes
     *    is the account's plan (live 15.09 on v3: Free — one a character, Starter — one per two).
     *  - `request_id` — the vendor's id of the call; lines of one dialogue share it, so distinct ids count the calls.
     *  - `line_ref` may name a filler: `p3.f2` — the frame of phrase 3 said with its second filler.
     *
     * Nullable, no default: a row written before this migration has no known charge, and a zero would lie. Read by
     * scene (the unique index leads with `scene_id`), so no new index.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_line_audios ADD COLUMN characters integer NULL');
        DB::statement('ALTER TABLE plan_line_audios ADD COLUMN credits integer NULL');
        DB::statement('ALTER TABLE plan_line_audios ADD COLUMN request_id varchar(64) NULL');
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT IF EXISTS plan_line_audios_line_ref_check');
        DB::statement("ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_line_ref_check CHECK (line_ref ~ '^(x[0-9]+b?|[pv][0-9]+|p[0-9]+\\.f[0-9]+)$')");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM plan_line_audios WHERE line_ref ~ '^p[0-9]+\\.f[0-9]+$'");
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT IF EXISTS plan_line_audios_line_ref_check');
        DB::statement("ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_line_ref_check CHECK (line_ref ~ '^(x[0-9]+b?|[pv][0-9]+)$')");
        DB::statement('ALTER TABLE plan_line_audios DROP COLUMN IF EXISTS request_id');
        DB::statement('ALTER TABLE plan_line_audios DROP COLUMN IF EXISTS credits');
        DB::statement('ALTER TABLE plan_line_audios DROP COLUMN IF EXISTS characters');
    }
};
