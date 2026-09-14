<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A PHRASE IS A FRAME (наряд GEN-2a, `lesson_day.v4.4`): the unit keeps the frame it was said from.
     *
     * `frame_target` / `frame_native` / `frame_pronunciation_native` — the pattern with its `___`, in both
     * languages and read aloud; `frame_kind` — `answer` | `ask`; `slot` — `{hint_native, fillers:
     * [{target, native, pronunciation_native, in_dialogue}]}`, null for a frame without a slot. The
     * phrase's `text_target` / `text_native` / `pronunciation_native` stay what the cards, the voice and the
     * collection read: the frame said with the filler of its first dialogue line. `used_in` — where the
     * lesson says a word or a chunk (`["p3", "A5"]`).
     *
     * All nullable, no defaults: metadata-only ALTERs. No index: read with the scene's terms, by the
     * existing (scene_id, position) index; JSON is read, never filtered on.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_terms ADD COLUMN frame_target text NULL');
        DB::statement('ALTER TABLE plan_terms ADD COLUMN frame_native text NULL');
        DB::statement('ALTER TABLE plan_terms ADD COLUMN frame_pronunciation_native text NULL');
        DB::statement('ALTER TABLE plan_terms ADD COLUMN frame_kind varchar(8) NULL');
        DB::statement('ALTER TABLE plan_terms ADD COLUMN slot jsonb NULL');
        DB::statement('ALTER TABLE plan_terms ADD COLUMN used_in jsonb NULL');
        DB::statement("ALTER TABLE plan_terms ADD CONSTRAINT plan_terms_frame_kind_check CHECK (frame_kind IS NULL OR frame_kind IN ('answer','ask'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_terms DROP CONSTRAINT IF EXISTS plan_terms_frame_kind_check');
        foreach (['used_in', 'slot', 'frame_kind', 'frame_pronunciation_native', 'frame_native', 'frame_target'] as $column) {
            DB::statement("ALTER TABLE plan_terms DROP COLUMN IF EXISTS {$column}");
        }
    }
};
