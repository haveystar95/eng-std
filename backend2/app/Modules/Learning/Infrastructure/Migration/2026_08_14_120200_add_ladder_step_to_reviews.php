<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which rung of the acquisition ladder the card that produced this answer was dealt at.
     *
     * Not decoration, and not derivable after the fact: the pair's rung MOVES the moment the answer
     * is folded, so a log without it cannot say afterwards what the card actually asked. It is also
     * what tells the grader that a step-1 answer is graded by IDENTITY (the learner tapped an
     * option; the key is the card's own term id) rather than as text — see the answer-key rule in
     * `.claude/skills/learning-srs`.
     *
     * Nullable, because every existing row predates the ladder and because a `known` verification
     * has no rung at all. `intro` is NOT represented here: it produces no review row (see
     * `term_exposures`), so the reviews check constraint on `exercise_mode` is deliberately left
     * alone — the log holds real retrievals only.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->smallInteger('ladder_step')->nullable()->after('exercise_mode');
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_ladder_step_check CHECK (ladder_step IS NULL OR ladder_step BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_ladder_step_check');
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('ladder_step');
        });
    }
};
