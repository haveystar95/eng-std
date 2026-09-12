<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PLAN'S JOURNAL (наряд PLAN-UI-3) — append-only facts: plan ready, day ready, day passed,
     * route rebuilt shorter, event today, event passed. The order called it `learning_plan_events`
     * after the deleted first plan chain; the live tables are `plans` / `plan_days`, so it is
     * `plan_events`.
     *
     * FKs follow the other plan tables: the plan cascades (only the account eraser hard-deletes a
     * plan; a learner's delete is a status), a removed day row nulls `day_id` and the line keeps its
     * `day_number`.
     *
     * Indexes are the queries: (plan_id, kind) — «is there already an event_today?» on every tick;
     * (plan_id, occurred_at) — a plan's journal in order; (user_id) — the eraser. Two partial unique
     * indexes make the once-only facts once-only in the schema, so an overlapping tick or a retried
     * job hits ON CONFLICT DO NOTHING instead of doubling a line.
     */
    public function up(): void
    {
        Schema::create('plan_events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('plan_id', 26);
            $table->char('day_id', 26)->nullable();
            $table->integer('day_number')->nullable();
            $table->string('kind', 32);
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('day_id')->references('id')->on('plan_days')->nullOnDelete();
        });
        DB::statement("ALTER TABLE plan_events ADD CONSTRAINT plan_events_kind_check CHECK (kind IN ('plan_ready','day_ready','day_passed','days_skipped_rebuilt','event_today','event_passed'))");
        DB::statement('CREATE INDEX plan_events_plan_kind_idx ON plan_events (plan_id, kind)');
        DB::statement('CREATE INDEX plan_events_plan_time_idx ON plan_events (plan_id, occurred_at)');
        DB::statement('CREATE INDEX plan_events_user_idx ON plan_events (user_id)');
        DB::statement("CREATE UNIQUE INDEX plan_events_once_uidx ON plan_events (plan_id, kind) WHERE kind IN ('plan_ready','event_today','event_passed')");
        DB::statement("CREATE UNIQUE INDEX plan_events_day_passed_uidx ON plan_events (plan_id, day_number) WHERE kind = 'day_passed'");
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_events');
    }
};
