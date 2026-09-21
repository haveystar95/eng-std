<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE JOURNAL OF WALKED STAGES (наряд CONV-2, п. 2; дух решения 298): one row per day and stage, written when the
     * stage is walked and never changed or removed. The first user is the talk — the sixth stage has no cards, and
     * «Ещё раз» after a walked talk made the stage «идёт» again while its state was read off the day's latest talk.
     *
     * Append-only: no `updated_at`, and the unique index on (day, stage) is the rule «walked once» as well as the
     * access path of both reads — one day's stage, and one stage over the days of a plan. The talk that walked it is
     * named (`conversation_id`) because it is the day's result: what returns tomorrow and what «Что было хорошо» says.
     *
     * Nothing is backfilled here: the talks that ended before the table existed are written by
     * `php artisan plan:reconcile-talks`, a command that reads the journal of talks and says what it wrote.
     */
    public function up(): void
    {
        Schema::create('plan_stage_passages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->char('day_id', 26);
            $table->string('stage', 16);
            $table->char('conversation_id', 26)->nullable();
            $table->timestampTz('passed_at');
            $table->timestampTz('created_at');

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('day_id')->references('id')->on('plan_days')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->unique(['day_id', 'stage'], 'plan_stage_passages_day_stage_uidx');
        });
        DB::statement("ALTER TABLE plan_stage_passages ADD CONSTRAINT plan_stage_passages_stage_check CHECK (stage IN ('words','phrases','dialogue','listen','speak','recall','conversation'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_stage_passages');
    }
};
