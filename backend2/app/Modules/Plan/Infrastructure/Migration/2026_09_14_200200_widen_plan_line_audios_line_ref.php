<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * EVERYTHING A DAY SAYS HAS A FILE NAME (DAY-UI-3): `x3` the partner's line of exchange 3 (as
     * before), `x3b` the learner's line, `p2` a phrase (as before), `v5` a word or a chunk. The CHECK
     * held the two kinds the server voiced until now; it widens to the four.
     *
     * Down: the rows the old CHECK cannot hold — the learner's lines and the words — go, then the old
     * CHECK comes back. Their files stay on the disk, unnamed; take the backup first, as for any write.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT IF EXISTS plan_line_audios_line_ref_check');
        DB::statement("ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_line_ref_check CHECK (line_ref ~ '^(x[0-9]+b?|[pv][0-9]+)$')");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM plan_line_audios WHERE line_ref !~ '^[xp][0-9]+$'");
        DB::statement('ALTER TABLE plan_line_audios DROP CONSTRAINT IF EXISTS plan_line_audios_line_ref_check');
        DB::statement("ALTER TABLE plan_line_audios ADD CONSTRAINT plan_line_audios_line_ref_check CHECK (line_ref ~ '^[xp][0-9]+$')");
    }
};
