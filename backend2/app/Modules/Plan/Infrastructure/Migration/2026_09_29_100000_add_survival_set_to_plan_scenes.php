<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE SURVIVAL SET OF A SCENE (наряд GEN-4, `plan-builder-v2.1`): what the learner must SAY — `must_say`, a list of
     * `{text, slot}` (the intention and the part of its sentence that varies; slot null for «none») — and what they must
     * UNDERSTAND — `must_understand`, a list of `{text}`. The day's skeleton is built from it, and a plan extended later tells
     * the model the sets of the scenes it has. A scene written before the set existed keeps both null — no backfill: its
     * brief never had one. Nullable, no default, no index: read with its scene by the plan's own index (the scenes of a plan
     * are read by `plan_id`; nothing selects on these columns). Down drops only these two columns.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN must_say jsonb NULL');
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN must_understand jsonb NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS must_understand');
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS must_say');
    }
};
