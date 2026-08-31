<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_days.generation_violations` — WHAT THE NEXT ATTEMPT IS TOLD.
     *
     * A day gets two attempts and the second one is worth its money only to the extent that it
     * knows what was wrong with the first. It has always been told SOMETHING: `fail_reason` carries
     * the verdict as a sentence. But that column is for a person reading the plan screen — it is
     * truncated to 500 characters, and the live «собеседование» day produced eighteen violations in
     * one answer, of which about four fitted.
     *
     * So the verdict is stored twice, on purpose, because it has two readers who want different
     * things: `fail_reason` is prose for a human, this is a list for the next prompt.
     *
     * ACCUMULATED, not replaced. The run this column comes from watched the second answer fix
     * exactly the four defects it was told about and introduce five new ones, and the fourth answer
     * do the same thing again — a model told only the last thing that broke re-breaks what it had
     * fixed. Cleared when the day is written: a list of things wrong with a day that no longer
     * exists is noise.
     *
     * `jsonb` and not `text`: it is a list, it is read back as a list, and Postgres has a type for
     * that. No index — nothing ever queries by it; it is read by primary key with the day.
     */
    public function up(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->jsonb('generation_violations')->nullable()->after('fail_reason');
        });
    }

    public function down(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->dropColumn('generation_violations');
        });
    }
};
