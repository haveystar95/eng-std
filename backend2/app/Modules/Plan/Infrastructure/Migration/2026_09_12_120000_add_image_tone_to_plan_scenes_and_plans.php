<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE TONE OF A PHOTO (PLAN-UI-3): the vendor's average colour (`#978E82`) of a scene's photo
     * and of the plan cover, written in the same conditional UPDATE as the photo itself, so the
     * client can paint the circle before the bytes arrive.
     *
     * Nullable, no default: a metadata-only ALTER, no table rewrite. Scenes photographed before
     * this column existed get their tone from `plan:images-backfill`. The CHECK holds the format
     * the value object already normalises to. No index: the column is read with its row, never
     * filtered on.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN image_tone varchar(7) NULL');
        DB::statement("ALTER TABLE plan_scenes ADD CONSTRAINT plan_scenes_image_tone_check CHECK (image_tone IS NULL OR image_tone ~ '^#[0-9A-F]{6}$')");
        DB::statement('ALTER TABLE plans ADD COLUMN cover_image_tone varchar(7) NULL');
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_cover_image_tone_check CHECK (cover_image_tone IS NULL OR cover_image_tone ~ '^#[0-9A-F]{6}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plans DROP CONSTRAINT IF EXISTS plans_cover_image_tone_check');
        DB::statement('ALTER TABLE plans DROP COLUMN IF EXISTS cover_image_tone');
        DB::statement('ALTER TABLE plan_scenes DROP CONSTRAINT IF EXISTS plan_scenes_image_tone_check');
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS image_tone');
    }
};
