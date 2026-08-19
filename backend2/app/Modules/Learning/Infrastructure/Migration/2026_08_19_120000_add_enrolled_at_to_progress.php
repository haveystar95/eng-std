<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE PERSONAL POOL, as one nullable timestamp on the pair.
     *
     * The chapter this migration opens splits the LIBRARY from the QUEUE: a collection goes back to
     * being a catalogue of a topic, and what the trainer actually works through is the learner's own
     * pool of words. The obvious-looking implementation — a «Мои слова» collection — was rejected
     * before a line was written, and the reason is worth keeping next to the column: a collection
     * would duplicate every term it holds, need a tombstone for every removal, and give the same
     * word two progress stories depending on which collection it was answered in. Progress is keyed
     * by (user_id, term_id); membership of the pool is a fact about the same pair, so it belongs in
     * the same row.
     *
     *   enrolled_at IS NOT NULL   the pair is IN the pool — sessions may deal it
     *   enrolled_at IS NULL       it is in the catalogue only — the trainer never sees it
     *
     * Removal is a PAUSE, never an erasure: «убрать из изучения» writes NULL here and touches
     * nothing else. The append-only review log, the ladder rung and the SM-2 schedule stay exactly
     * as they were, so re-enrolling the pair continues from the rung it had earned rather than from
     * an intro card. That is the whole reason this is a nullable timestamp and not a delete.
     *
     * BACKFILL. Every pair that exists today was created by a deliberate act — a triage swipe or an
     * answered card — so it is enrolled, stamped with the moment its row appeared (`created_at`)
     * rather than with «now», so «когда я это начал» survives the migration. The one exception is a
     * pair the learner swiped «знаю»: that verdict never meant «учи это», it meant the opposite, and
     * its row exists only to carry a verification check. Those stay OUT of the pool (NULL) — and
     * they are recognised by `state = 'known'`, not by `acquisition`, because a known pair is stored
     * as `graduated` (the ladder has no say over it) and would otherwise be indistinguishable from a
     * word that has honestly graduated off the recognition rungs. A known pair whose verification
     * has since FAILED is already `state = 'learning'`, and that is a word being learned for real —
     * it is enrolled, correctly, by the same rule.
     */
    public function up(): void
    {
        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->timestampTz('enrolled_at')->nullable()->after('last_reviewed_at');
        });

        DB::statement("UPDATE user_term_progress SET enrolled_at = COALESCE(created_at, now()) WHERE state <> 'known'");

        // Selection now asks TWO questions of every row — «is it in the pool» and «is it owed a
        // card» — so the two partial indexes the ladder added are re-cut with the pool predicate in
        // them. Cheaper as well as correct: the index no longer covers the rows the query can never
        // return.
        DB::statement('DROP INDEX IF EXISTS user_term_progress_ladder_idx');
        DB::statement('DROP INDEX IF EXISTS user_term_progress_schedulable_idx');
        DB::statement("CREATE INDEX user_term_progress_ladder_idx ON user_term_progress (user_id) WHERE acquisition <> 'graduated' AND enrolled_at IS NOT NULL");
        DB::statement("CREATE INDEX user_term_progress_schedulable_idx ON user_term_progress (user_id, due_at NULLS FIRST) WHERE acquisition = 'graduated' AND enrolled_at IS NOT NULL");
        // …and the pool itself, read whole by «Мои слова» and by an unscoped session: newest
        // enrolment last, which is the order the screen lists them in.
        DB::statement('CREATE INDEX user_term_progress_pool_idx ON user_term_progress (user_id, enrolled_at) WHERE enrolled_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_term_progress_pool_idx');
        DB::statement('DROP INDEX IF EXISTS user_term_progress_schedulable_idx');
        DB::statement('DROP INDEX IF EXISTS user_term_progress_ladder_idx');
        DB::statement("CREATE INDEX user_term_progress_ladder_idx ON user_term_progress (user_id) WHERE acquisition <> 'graduated'");
        DB::statement("CREATE INDEX user_term_progress_schedulable_idx ON user_term_progress (user_id, due_at NULLS FIRST) WHERE acquisition = 'graduated'");

        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->dropColumn('enrolled_at');
        });
    }
};
