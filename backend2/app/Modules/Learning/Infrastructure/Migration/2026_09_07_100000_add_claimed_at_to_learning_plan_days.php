<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_days.claimed_at` — WHEN A WORKER TOOK THE DAY (вердикт владельца по GEN-1).
     *
     * A day whose worker died mid-generation stayed `generating` for ever: `claim()` refuses
     * anything that is not `pending`/`failed`, and nothing measured how long «right now» had been
     * going on (`docs/research/gen-1/pipeline.md` §1.3). The stamp is what a timeout is measured
     * from: a day claimed more than `learning.plan.generation_stale_minutes` ago is re-queued by
     * the next poll or plan read, and after the second stale claim it is `failed` with a reason
     * ({@see \App\Modules\Learning\Domain\Entity\PlanDay::reclaimStale()}).
     *
     * `updated_at` would not do: every save moves it, and a save is not a claim. Nullable, and null
     * on every day nobody has taken yet.
     */
    public function up(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->timestampTz('claimed_at')->nullable()->after('generation_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->dropColumn('claimed_at');
        });
    }
};
