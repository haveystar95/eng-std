<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `generation_requests.plan_id` pointed at the first learning plan's table, which is gone. The
     * rows themselves stay (a ledger of money spent, see the `purpose` migration); only the dangling
     * reference goes. A no-op on a fresh database.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('generation_requests', 'plan_id')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS generation_requests_plan_idx');
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->char('plan_id', 26)->nullable()->after('collection_id');
        });
        DB::statement('CREATE INDEX generation_requests_plan_idx ON generation_requests (plan_id) WHERE plan_id IS NOT NULL');
    }
};
