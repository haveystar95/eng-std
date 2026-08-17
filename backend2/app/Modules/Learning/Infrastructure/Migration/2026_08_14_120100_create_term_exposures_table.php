<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The intro log: a term was SHOWN to a user. Append-only, like `reviews` and `term_triages`.
     *
     * A separate table rather than a row in `reviews` with a null grade, because the review log is
     * the source of retention, the per-mode latency medians and every "how well do you know this"
     * figure in the app — and an intro asks the learner for nothing. A row there would be a
     * retrieval that never happened, quietly inflating all of them.
     *
     * The PRIMARY KEY is the PAIR, not an event id. A term is introduced once; a second intro of
     * the same word is not a second fact, it is the same fact re-uploaded by a device that lost its
     * acknowledgement. Making the pair the key means idempotency is a property of the schema rather
     * than of whichever handler happens to be running — the write is `insertOrIgnore` and the
     * FIRST `shown_at` is the one kept, which is the one that is true.
     */
    public function up(): void
    {
        Schema::create('term_exposures', function (Blueprint $table): void {
            $table->char('user_id', 26);
            $table->char('term_id', 26);
            // Which session showed it. Nullable: an offline intro whose session the server has
            // never seen is adopted like an offline practice run, but a session can also be gone.
            $table->char('session_id', 26)->nullable();
            $table->timestampTz('shown_at');
            $table->timestampTz('created_at')->nullable();   // server receipt time

            $table->primary(['user_id', 'term_id']);
            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('study_sessions')->nullOnDelete();
            // "What did this user meet, and when" — the read behind any future first-meeting report.
            $table->index(['user_id', 'shown_at'], 'term_exposures_user_shown_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_exposures');
    }
};
