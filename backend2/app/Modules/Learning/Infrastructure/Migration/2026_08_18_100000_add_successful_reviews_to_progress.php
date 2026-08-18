<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ladder's OWN counter for rungs 3–5 — successful reviews, not scheduler calls (QA-18).
     *
     * Rungs 3–5 were read off `reps`, which the SM-2 scheduler increments on every branch it takes,
     * `again` included. It is an honest counter of one thing (how many times the scheduler has been
     * called — which is what drives the mode rotation, and still does) and a dishonest counter of
     * another (how well the pair is known). The ladder needed the second, and read the first.
     *
     * The effect was not academic. An `again` in the learning state schedules the pair back with a
     * 0-day interval, so it returns inside the same session; four misses and two hits therefore
     * read as `reps = 6` and the word was promoted to DICTATION — the hardest card the app has —
     * on the strength of having been forgotten. The owner had live examples: `antipyretic` sat at
     * rung 5 off 4 misses and 2 hits, `consult a pharmacist` at rung 4 off 4 and 2.
     *
     * So: a new column, and `reps` left exactly alone. Two counters because there are genuinely two
     * questions; sharing one column was the bug.
     *
     * WHAT INCREMENTS IT (the one rule, enforced in SubmitReviewsHandler and reproduced by the
     * backfill below): a non-practice review, answered correctly, of a pair that is already
     * `graduated`. `hard` counts — «recalled it with a stumble» is a recall. `again` does not count
     * and does not RESET: a rung, once earned, is not taken back. That is a deliberate
     * simplification next to FSRS, which would model the decay; the rung only decides which
     * trainers a word may appear in, and demoting it out of typing for one bad evening buys nothing
     * and costs the learner the trainer that was working.
     *
     * BACKFILL — deterministic, from the append-only review log, which is why it can be a plain
     * SQL count rather than a replay:
     *
     *   is_practice = false   free practice never touches progress, so it never earned a rung
     *   is_correct  = true    the same column the log already carries; `hard` is correct in it
     *   ladder_step IS NULL OR ladder_step >= 3
     *                         rungs 1–2 are the recognition steps, which are not graduated and so
     *                         cannot have incremented the counter. NULL is the pre-ladder history —
     *                         all of it from before the recognition rungs existed, i.e. all of it
     *                         graduated — and counts.
     *
     * `ladder_step` is the rung the CLIENT says the card was dealt at, and using it here rather
     * than re-deriving the acquisition at each point in history is the whole reason this is
     * deterministic: the log records what was shown, and what was shown is what was earned.
     */
    public function up(): void
    {
        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->integer('successful_reviews')->default(0)->after('reps');
        });

        DB::statement('ALTER TABLE user_term_progress ADD CONSTRAINT user_term_progress_successful_reviews_check CHECK (successful_reviews >= 0)');

        $this->backfill();
    }

    /**
     * The backfill, as its own method so a test can run the REAL statement rather than a copy of
     * it. A one-shot migration is exactly the code nobody re-reads, and this one decides what rung
     * every existing word starts on.
     */
    public function backfill(): void
    {
        DB::statement(<<<'SQL'
            UPDATE user_term_progress p
               SET successful_reviews = COALESCE((
                   SELECT count(*)
                     FROM reviews r
                    WHERE r.user_id = p.user_id
                      AND r.term_id = p.term_id
                      AND r.is_practice = false
                      AND r.is_correct = true
                      AND (r.ladder_step IS NULL OR r.ladder_step >= 3)
               ), 0)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_term_progress DROP CONSTRAINT IF EXISTS user_term_progress_successful_reviews_check');

        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->dropColumn('successful_reviews');
        });
    }
};
