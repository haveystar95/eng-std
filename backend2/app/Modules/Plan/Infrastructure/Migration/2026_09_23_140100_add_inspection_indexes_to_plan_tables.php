<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The access paths of the admin's plan page (наряд ADM-1), indexes only:
 * - `plans_code_idx` — a plan found by its code, characters 5–10 of its ULID (`substr(id, 5, 6)`), the name reports and
 *   the panel's ⌘K use;
 * - `conversations_plan_started_idx` — a plan's talks in order (the table had indexes by day and by user only);
 * - `plan_stage_passages_plan_idx` — a plan's walked stages (the table had its unique (day, stage) only).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS plans_code_idx ON plans ((substr(id, 5, 6)))');
        DB::statement('CREATE INDEX IF NOT EXISTS conversations_plan_started_idx ON conversations (plan_id, started_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS plan_stage_passages_plan_idx ON plan_stage_passages (plan_id, passed_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS plan_stage_passages_plan_idx');
        DB::statement('DROP INDEX IF EXISTS conversations_plan_started_idx');
        DB::statement('DROP INDEX IF EXISTS plans_code_idx');
    }
};
