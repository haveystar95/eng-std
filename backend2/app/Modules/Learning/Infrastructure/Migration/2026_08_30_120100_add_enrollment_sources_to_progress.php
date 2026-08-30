<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WHY a pair is in the pool — the list, not the last answer.
     *
     * `enrolled_at` says the pair is being studied. It does not say who put it there, and until now
     * nothing did. That was fine while every door into the pool was the same kind of act — a swipe
     * or a tap, both of them the learner's, both of them undoable by the learner at any moment.
     *
     * A plan is a different kind of door. «Убрать из изучения» on a word that day 1 of an active
     * plan introduced is not a preference, it is a hole in a mechanism with a deadline: the day
     * promised an ability, the conversation checks that ability, and the word is what the ability is
     * made of. So the pool has to be able to answer «кто это сюда положил» — and to answer it with a
     * LIST, because the same word can be both something the learner saved by hand in June and
     * something a plan needs in August. Removing the plan must not remove the learner's own reason
     * for having it, and a single `source` column would have to choose one of the two and lie.
     *
     *   ["manual"]                 the learner put it here (a tap, or a swipe)
     *   ["triage"]                 a triage verdict put it here
     *   ["manual","plan:01J…"]     both, and it survives the plan ending
     *
     * BACKFILL. Every enrolled pair that exists today got here through one of the two learner acts,
     * and the migration writes `["manual"]` for all of them rather than guessing which. «triage» is
     * derivable — `term_triages` is append-only and still holds every swipe — but derivable is not
     * the same as known, and a backfill that guesses would put a wrong reason on a real row where
     * the honest answer «a person did this» is already true of every one of them.
     *
     * NOT-ENROLLED rows get `[]`, not `["manual"]`. A `known` swipe leaves a row that is
     * deliberately outside the pool ({@see TermProgress::knownFromTriage}); stamping it with a
     * source would claim an enrolment that never happened.
     */
    public function up(): void
    {
        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->jsonb('enrollment_sources')->default('[]')->after('enrolled_at');
        });

        DB::statement("UPDATE user_term_progress SET enrollment_sources = '[\"manual\"]'::jsonb WHERE enrolled_at IS NOT NULL");

        // The strictness question — «is this pair held by an active plan» — is asked per pair, on
        // the pair's own row, so it needs no index of its own. The plan asks the opposite question
        // («which terms does day N hold») of `collection_items`, which is already indexed for it.
    }

    public function down(): void
    {
        Schema::table('user_term_progress', function (Blueprint $table): void {
            $table->dropColumn('enrollment_sources');
        });
    }
};
