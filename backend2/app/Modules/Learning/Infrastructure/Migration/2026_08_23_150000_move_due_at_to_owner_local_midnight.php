<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * QA-BUG-1 — one-shot correction of the due dates written while the offset was being dropped.
     *
     * The scheduler always floored a day-scale due to 00:00 in the LEARNER'S zone; the write then
     * bound that instant as a bare `Y-m-d H:i:s` string with no offset (see TermProgressMapper),
     * so Postgres read it as UTC. A Bucharest midnight went into the column as midnight UTC and
     * came back to the phone as 03:00 — every card three hours late, and the row is now a lie
     * about when the learner's day starts.
     *
     * The correction is the inverse of the loss, and needs no history: take the stored wall clock
     * (which IS the intended local one) and re-read it in the owner's zone.
     *
     * WHICH ROWS. Exactly those sitting on UTC midnight — that is the fingerprint the broken floor
     * left, and nothing else lands there:
     *
     *   * a 0-day step ("again this session") is due at the exact moment of the answer and must
     *     stay there — flooring it would push a card the learner just missed out of the session;
     *   * a `known` verification is scheduled 90 days from the answer, time of day and all
     *     (TermProgress::passVerification) — it is not a day-scale due and has no local midnight
     *     to lie about.
     *
     * On the owner's data that is 105 of 178 dated rows: every `review` row, the day-scale half of
     * `learning`, and no `known` row. The other 73 are the two exact-moment cases above.
     *
     * A learner with no profile, an empty zone, or a zone Postgres does not recognise is skipped by
     * the join: for them UTC IS the local zone (the app's own documented fallback in
     * IdentityLearnerProfileReader), so the stored value is already right.
     *
     * IRREVERSIBLE by design — see down().
     */
    public function up(): void
    {
        $this->correct();
    }

    /**
     * The correction, as its own method so a test runs the REAL statement rather than a copy of it.
     * A one-shot migration is exactly the code nobody re-reads, and this one moves every scheduled
     * card the owner has.
     */
    public function correct(): void
    {
        DB::statement(<<<'SQL'
            UPDATE user_term_progress p
               SET due_at = (p.due_at AT TIME ZONE 'UTC') AT TIME ZONE z.name
              FROM profiles pr
              JOIN pg_timezone_names z ON z.name = pr.timezone
             WHERE pr.user_id = p.user_id
               AND p.due_at IS NOT NULL
               AND p.due_at = date_trunc('day', p.due_at AT TIME ZONE 'UTC') AT TIME ZONE 'UTC'
        SQL);
    }

    /**
     * Deliberately empty. Undoing this would mean re-breaking correct data, and it cannot even be
     * done honestly: after the correction a row's due_at is no longer on UTC midnight, so there is
     * no fingerprint left to tell a corrected row from one the fixed code wrote. The schema is
     * untouched, so a rollback past this point loses nothing.
     */
    public function down(): void {}
};
