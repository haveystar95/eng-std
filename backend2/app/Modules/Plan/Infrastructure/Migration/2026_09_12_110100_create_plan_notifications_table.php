<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PLAN'S DELIVERY LOG (наряд PLAN-UI-3) — append-only, one line per letter that was sent
     * or tried: `sent`, `not_sent` (dry mode, no APNs key), `failed`, `no_token`.
     *
     * «Не чаще раза в сутки» lives in the schema: a partial unique index on (user_id, local_date)
     * for `daily_reminder` — `local_date` is the learner's own date, so the day boundary is theirs.
     * It is also the index the reminder check reads. (plan_id, created_at) serves a plan's letters,
     * (user_id, created_at) the eraser and a learner's history. The plan FK cascades like the other
     * plan tables; an erased journal line nulls `event_id`.
     *
     * `day_number` is beyond the order's column list: the letter about «День 3» has to say which
     * day when someone reads the log, and the plan's days can be re-laid after it was sent.
     */
    public function up(): void
    {
        Schema::create('plan_notifications', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('plan_id', 26);
            $table->char('event_id', 26)->nullable();
            $table->string('kind', 32);
            $table->integer('day_number')->nullable();
            $table->date('local_date');
            $table->string('status', 16);
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('plan_events')->nullOnDelete();
        });
        DB::statement("ALTER TABLE plan_notifications ADD CONSTRAINT plan_notifications_kind_check CHECK (kind IN ('plan_ready','day_ready','daily_reminder','event_today','days_skipped_rebuilt'))");
        DB::statement("ALTER TABLE plan_notifications ADD CONSTRAINT plan_notifications_status_check CHECK (status IN ('sent','not_sent','failed','no_token'))");
        DB::statement("CREATE UNIQUE INDEX plan_notifications_daily_reminder_uidx ON plan_notifications (user_id, local_date) WHERE kind = 'daily_reminder'");
        DB::statement('CREATE INDEX plan_notifications_plan_idx ON plan_notifications (plan_id, created_at)');
        DB::statement('CREATE INDEX plan_notifications_user_idx ON plan_notifications (user_id, created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_notifications');
    }
};
