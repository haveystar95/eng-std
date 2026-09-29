<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE SKELETON OF A SCENE'S DAY (наряд GEN-4, 3.2): the first of the day's two stages — frames with the `must_say` items
     * they serve, partner lines with their items and pairs, the vocabulary — as the skeleton prompt's OUTPUT SCHEMA writes it,
     * after its check and its repairs; kept beside `lesson_json`, which is assembled from it and the dialogue. A repair of a
     * dialogue card and a rebuild read it; the admin will. A lesson written before (in one call) keeps null. Nullable, no
     * default, no index: read with its scene by the plan's own index. Down drops only this column.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN skeleton_json jsonb NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS skeleton_json');
    }
};
