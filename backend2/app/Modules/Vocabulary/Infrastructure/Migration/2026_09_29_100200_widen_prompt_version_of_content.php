<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A PROMPT VERSION AS THE PROMPTS ARE NAMED NOW (наряд GEN-4). `prompt_version` of the content tables was made sixteen
     * wide for `v10`, `v15.2` — the versions of the Generation prompts. The plan hands a day's words to its collection
     * under the version of the prompts that wrote them: since PROMPTS-1 a prompt's file name (`lesson_day.v4.10`, sixteen —
     * the last one that fit), since GEN-4 the day's two stages (`lesson_skeleton.v1+lesson_dialogue.v1`, thirty-seven).
     * A longer version is no term written, and the collection missed the day's words. Sixty-four, the width of a prompt
     * version everywhere else in the schema (`plan_scenes`, `plan_check_counters`). Postgres lengthens a varchar in its
     * catalogue alone: no table is rewritten, no index rebuilt.
     */
    private const TABLES = ['terms', 'term_translations', 'term_examples'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN prompt_version TYPE varchar(64)");
        }
    }

    public function down(): void
    {
        // One-way: a row written in between may be longer than sixteen.
    }
};
