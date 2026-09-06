<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `terms.speaking_keys` — WHAT ELSE COUNTS WHEN A PLAN LINE IS SPOKEN (наряд GEN-1, канон Y4).
     *
     * `speaking_key` (01.09) made a spoken line gradeable on its piece instead of on all fifteen
     * words. The owner's next screen was the other half of the same problem: the piece is ONE
     * string, and a learner who said the reply a simpler way — «two years» for «I have two years of
     * commercial experience» — was marked wrong for not producing the exact filler. So the day now
     * writes 1–2 alternative forms beside the key (P2 v0.7, `you.speaking_keys[]`), and the grader
     * accepts any of them ({@see \App\Modules\Learning\Application\Command\SubmitReviewsHandler}).
     *
     * A JSON list and not a second table: the list is written once with the day, read with the
     * card, never queried across terms, and is at most two strings long. Nullable, and null is the
     * honest state for every term that never came from a plan day — and for every plan line written
     * before v0.7, which the grader keeps judging by `speaking_key` alone. No backfill: the old days
     * are not migrated (правило наряда).
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->jsonb('speaking_keys')->nullable()->after('speaking_key');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn('speaking_keys');
        });
    }
};
