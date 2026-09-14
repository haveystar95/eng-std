<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A DAY IS READY WITH ITS PICTURES ON IT (DAY-UI-3): between «the lesson is written» and «ready»
     * the scene is `illustrating` — its photos are being found. The wire calls it `building`.
     *
     * Only the CHECK changes; `plan_scenes_plan_lesson_idx (plan_id, lesson_status)` serves the new word
     * as it serves the others. Down: a scene caught illustrating is ready (its lesson is written; the
     * photos are best effort), then the old CHECK comes back.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes DROP CONSTRAINT IF EXISTS plan_scenes_lesson_status_check');
        DB::statement("ALTER TABLE plan_scenes ADD CONSTRAINT plan_scenes_lesson_status_check CHECK (lesson_status IN ('pending','building','illustrating','ready','failed'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE plan_scenes SET lesson_status = 'ready' WHERE lesson_status = 'illustrating'");
        DB::statement('ALTER TABLE plan_scenes DROP CONSTRAINT IF EXISTS plan_scenes_lesson_status_check');
        DB::statement("ALTER TABLE plan_scenes ADD CONSTRAINT plan_scenes_lesson_status_check CHECK (lesson_status IN ('pending','building','ready','failed'))");
    }
};
