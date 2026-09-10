<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `generation_requests.purpose` — what a ledger row is FOR.
     *
     * The column exists to keep products apart in one ledger: the daily QUOTA counts rows of this
     * table, and the PROMPT CACHE looks a finished row up by its normalised prompt — both of them
     * mean «a collection generation» and nothing else. Every row written before the column was a
     * collection generation, so the default is the fact and no backfill is needed.
     *
     * `plan` stays in the whitelist as a HISTORICAL value: the first learning plan wrote its model
     * calls here, those rows are money spent, and a ledger a delete can erase is not a ledger. The
     * plan that replaced it records its own cost on its own rows (`plans`, `plan_scenes`) and never
     * writes here again.
     *
     * The file keeps its original name on purpose — it is already applied on the dev database
     * under this name, and the column it used to add beside this one (`plan_id`) is dropped by
     * `2026_09_10_100200_drop_plan_id_from_generation_requests`.
     */
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->string('purpose', 16)->default('generation')->after('status');
        });

        DB::statement('ALTER TABLE generation_requests ALTER COLUMN purpose SET NOT NULL');
        DB::statement(
            'ALTER TABLE generation_requests ADD CONSTRAINT generation_requests_purpose_check '
            . "CHECK (purpose IN ('generation','plan'))"
        );

        // «Что стоило это за месяц, по продуктам» — the cost screen's sum.
        DB::statement('CREATE INDEX generation_requests_purpose_idx ON generation_requests (purpose, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS generation_requests_purpose_idx');
        DB::statement('ALTER TABLE generation_requests DROP CONSTRAINT IF EXISTS generation_requests_purpose_check');

        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }
};
