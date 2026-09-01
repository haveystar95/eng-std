<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_days.repair_calls` — THE REPAIR CALLS, COUNTED APART FROM THE DAY CALLS.
     *
     * `generation_attempts` was made to mean «money» (DECISIONS п. 199) and was then asked to carry
     * two different kinds of call at once, which it could only do by counting them differently
     * depending on how the day ended. The live run measured both halves of that on one plan
     * (`docs/research/e2e-sim-1.md`, Д-18):
     *
     *   день 1 — P2 + P2R, день `ready` → `generation_attempts = 1`. The repair was free.
     *   день 2 — P2 + P2 + P2R, день `failed` → `generation_attempts = 3`. The repair was an
     *            attempt, and the number went past a cap of two.
     *
     * Two P2 calls happened on that day and the cap did its job; what the row said was that three
     * attempts had been made, which is not a thing the budget has a name for. A counter that reads
     * differently on the successful and the failed path is not a counter.
     *
     * So the two calls are two columns. `generation_attempts` is the DAY calls — what
     * {@see \App\Modules\Learning\Domain\Entity\PlanDay::MAX_ATTEMPTS} caps, and the only thing that
     * decides `failed`. This is the REPAIR calls, charged identically whether the day was written or
     * refused, capped at one per run and therefore two per day
     * ({@see \App\Modules\Learning\Domain\Entity\PlanDay::MAX_REPAIR_CALLS}). Money is the sum, and
     * the sum is now readable — which it was not while one column meant both.
     *
     * Existing rows keep their number in `generation_attempts` and get `0` here: the split cannot be
     * reconstructed from history, and inventing it would be worse than a day that says its repairs
     * were free. The ledger (`generation_requests`) is the record that never lost them.
     *
     * No index — read by primary key with the day, never queried by.
     */
    public function up(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->unsignedSmallInteger('repair_calls')->default(0)->after('generation_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->dropColumn('repair_calls');
        });
    }
};
