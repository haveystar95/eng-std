<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * WHEN A SCENE'S LESSON WAS BUILT TO THE END (наряд FIX-4 §6): the moment the scene went `illustrating` → `ready` — the
     * lesson, its repairs, the seam judge and the photos all done, the same moment its `day_ready` line is written.
     * `generated_at` is NOT that and stays as it is: it is stamped with the moment the build BEGAN (ADM-1 found it so), and
     * the page of the plan read the end off the journal of events. Now it reads it here.
     *
     * The scenes built before are given the moment of their first `day_ready` line — the end the page read until now; a
     * scene without one (built before the journal of events, or never ready) keeps null. Nullable, no default, no index:
     * read with its plan by the plan's own index. Down drops only this column.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN built_at timestamp(0) with time zone NULL');
        DB::statement(<<<'SQL'
            UPDATE plan_scenes s
               SET built_at = r.first_ready
              FROM (SELECT e.payload->>'scene_id' AS scene_id, MIN(e.occurred_at) AS first_ready
                      FROM plan_events e
                     WHERE e.kind = 'day_ready' AND e.payload->>'scene_id' IS NOT NULL
                     GROUP BY e.payload->>'scene_id') r
             WHERE r.scene_id = s.id AND s.built_at IS NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS built_at');
    }
};
