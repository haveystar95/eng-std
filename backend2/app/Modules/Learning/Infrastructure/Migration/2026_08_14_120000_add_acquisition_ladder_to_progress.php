<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ACQUISITION LADDER as a second dimension on the pair, orthogonal to SM-2.
     *
     *   state (already here)  answers WHEN the pair comes back  → the scheduler owns it
     *   acquisition (new)     answers WHAT it comes back AS     → the admission matrix owns it
     *
     * Two columns, and not one SM-2 column is written by this migration. That is the whole design:
     * `state`, `ease_factor`, `interval_days`, `due_at`, `reps`, `lapses` and `last_reviewed_at`
     * keep whatever they hold, so no live interval is re-derived and no `known` verification is
     * lost. Reusing `state` for the ladder instead — the obvious-looking move — would have meant
     * rewriting `learning` rows to `review`, whose SM-2 branch multiplies `interval_days`; a row
     * arriving there with `interval_days = 0` multiplies to 0 and sticks at "due immediately"
     * forever.
     *
     * BACKFILL: every existing pair becomes `graduated`. They have all already been trained — the
     * recognition rungs are for words the app has never shown, and pushing a word someone has been
     * reviewing for weeks back to an intro card is the one outcome worse than no ladder.
     *
     * `learning_step` is the rung WHILE on the recognition steps, and it is not derivable from
     * `reps`: a failed step is re-queued as the same step but is still a real retrieval, so it is
     * logged, and anything derived from the log would push the pair up a rung it has not earned.
     */
    public function up(): void
    {
        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->string('acquisition', 16)->default('new')->after('state');
            $table->smallInteger('learning_step')->default(0)->after('acquisition');
        });

        // Text + CHECK, like `state` — widening it later is a constraint swap, not a table rewrite.
        DB::statement("ALTER TABLE user_term_progress ADD CONSTRAINT user_term_progress_acquisition_check CHECK (acquisition IN ('new','learning','graduated'))");
        DB::statement('ALTER TABLE user_term_progress ADD CONSTRAINT user_term_progress_learning_step_check CHECK (learning_step BETWEEN 0 AND 2)');

        DB::table('user_term_progress')->update(['acquisition' => 'graduated', 'learning_step' => 0]);

        // The default is `new` for rows written from here on (a pair introduced by an intro card is
        // inserted mid-ladder), which is why the backfill above is an explicit UPDATE and not a
        // default: the two populations want opposite values.

        // Selection now has a second entry point. A pair on the ladder is selectable because it is
        // unfinished, not because it is due — it has no `due_at` at all — so the hot query can no
        // longer be a pure due-date scan. Two partial indexes, one per reason.
        DB::statement("CREATE INDEX user_term_progress_ladder_idx ON user_term_progress (user_id) WHERE acquisition <> 'graduated'");
        // Graduated pairs: due ones, plus the freshly graduated that have never been scheduled
        // (due_at IS NULL) and are owed their first SRS review. NULLS FIRST matches the reader's
        // ordering so the index serves the sort as well as the filter.
        DB::statement("CREATE INDEX user_term_progress_schedulable_idx ON user_term_progress (user_id, due_at NULLS FIRST) WHERE acquisition = 'graduated'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_term_progress_schedulable_idx');
        DB::statement('DROP INDEX IF EXISTS user_term_progress_ladder_idx');
        DB::statement('ALTER TABLE user_term_progress DROP CONSTRAINT IF EXISTS user_term_progress_learning_step_check');
        DB::statement('ALTER TABLE user_term_progress DROP CONSTRAINT IF EXISTS user_term_progress_acquisition_check');

        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->dropColumn(['acquisition', 'learning_step']);
        });
    }
};
