<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE CAST OF A SCENE'S TWO VOICES (DAY-UI-3): the gender of the partner's voice; the learner's
     * lines, phrases and words take the other one.
     *
     * Written when the lesson is written (the role's gender from `lesson-v4`, the default otherwise),
     * and — once, conditionally — by the voice queue for a scene written before voices had genders.
     * Null = not cast yet; readers then use the default cast (the partner female), which is exactly the
     * voice every partner line bought before this наряд was bought in.
     *
     * Nullable, no default: a metadata-only ALTER. No index: read with its row, or by primary key.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN partner_voice_gender varchar(6) NULL');
        DB::statement("ALTER TABLE plan_scenes ADD CONSTRAINT plan_scenes_partner_voice_gender_check CHECK (partner_voice_gender IS NULL OR partner_voice_gender IN ('female','male'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_scenes DROP CONSTRAINT IF EXISTS plan_scenes_partner_voice_gender_check');
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS partner_voice_gender');
    }
};
