<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A SPOKEN LINE IS NAMED BY ITS UNIT REFERENCE, NOT BY AN EXCHANGE STEP (DAY-UI-2).
     *
     * The day window plays a phrase («прослушать» 28) from the same server audio as the partner's
     * lines, and a phrase has no step. The row is keyed by the reference every card already uses for
     * its unit: `x3` — the partner's line of exchange 3, `p2` — phrase 2. Existing rows are the
     * partner's lines and become `x<step>`; `step` goes, because a second name for the same line
     * would be a second way to look it up. The table is small (one row per spoken line), so the
     * UPDATE is fine in place.
     *
     * The unique constraint moves to (scene, reference, voice): the same line with the same voice is
     * still never bought twice.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_line_audios ADD COLUMN line_ref varchar(16) NULL');
        DB::statement("UPDATE plan_line_audios SET line_ref = 'x' || step");
        DB::statement('ALTER TABLE plan_line_audios ALTER COLUMN line_ref SET NOT NULL');
        DB::statement("ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_line_ref_check CHECK (line_ref ~ '^[xp][0-9]+$')");
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT plan_line_audios_uidx');
        DB::statement('ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_uidx UNIQUE (scene_id, line_ref, voice_key)');
        DB::statement('ALTER TABLE plan_line_audios DROP COLUMN step');
    }

    /** Back to steps: the phrases' rows have none and are dropped (their files stay on the disk). */
    public function down(): void
    {
        DB::statement('ALTER TABLE plan_line_audios ADD COLUMN step integer NULL');
        DB::statement("DELETE FROM plan_line_audios WHERE line_ref NOT LIKE 'x%'");
        DB::statement('UPDATE plan_line_audios SET step = substring(line_ref from 2)::integer');
        DB::statement('ALTER TABLE plan_line_audios ALTER COLUMN step SET NOT NULL');
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT plan_line_audios_uidx');
        DB::statement('ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_uidx UNIQUE (scene_id, step, voice_key)');
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT plan_line_audios_line_ref_check');
        DB::statement('ALTER TABLE plan_line_audios DROP COLUMN line_ref');
    }
};
