<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesQaUser;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * «Pretend N days have passed» for one QA account.
 *
 * The acquisition ladder is measured in days, so the scenario that matters most — a word climbing
 * from intro to dictation across a week of repeats — cannot be walked in an afternoon without
 * moving the clock. Moving the SERVER clock would move it for everything; moving the ROWS moves it
 * for one account, which is what a QA run needs.
 *
 * Every learning timestamp of that account is shifted BACK by N days, so «now» sits N days further
 * along their timeline: repeats fall due, the daily new-term quota is a fresh day's, activity and
 * streaks read as N days of history.
 *
 * ── What this is not ────────────────────────────────────────────────────────────────────────────
 *
 * It is a FORCED-TIME tool and it is destructive: it rewrites persisted timestamps in place, and
 * `reviews` / `term_triages` are append-only logs everywhere else in this codebase. That invariant
 * is about the DOMAIN — no handler, no endpoint, no job ever updates a logged row. This command is
 * outside the domain on purpose, refuses on any account not marked `is_qa`, and is the reason the
 * QA account is disposable: when the shifted history stops making sense, `qa:reset` it.
 *
 * Their answered_at is shifted along with everything else, deliberately. Leaving it alone would
 * keep today's introductions counted against today's quota while their schedule says a week has
 * passed — a half-moved clock is harder to reason about than either whole one.
 *
 * The client must FULL-RESYNC afterwards (pull-to-refresh clears the cursor) — the shifted rows are
 * older than the cursor the device holds, so a delta sync will not see them.
 *
 * Sibling of `batch:age-progress`, which does the same thing in HOURS for the owner's own account
 * and has no `is_qa` gate. This one is the QA-safe version: days, every learning table, one
 * account, and impossible on anyone real.
 */
final class QaTimeTravelCommand extends Command
{
    use ResolvesQaUser;

    protected $signature = 'qa:time-travel
        {user : the QA account — email or ULID}
        {--days= : how many days to pretend have passed, e.g. --days=+3}
        {--force : skip the confirmation prompt (non-interactive runs)}';

    protected $description = 'QA(non-prod): shift one QA account\'s learning timestamps back N days, so N days appear to have passed';

    public function handle(): int
    {
        $days = $this->days();
        if ($days === null) {
            return self::FAILURE;
        }

        $user = $this->resolveQaUser((string) $this->argument('user'));
        if ($user === null) {
            return self::FAILURE;
        }

        $userId = (string) $user->id;
        $counts = $this->rowCounts($userId);

        $this->warn("FORCED-TIME: about to move {$user->email} forward by {$days} day(s) by ageing every learning row.");
        foreach ($counts as $table => $n) {
            $this->line(sprintf('  %-20s %5d rows', $table, $n));
        }

        if (! $this->confirmedByForceOrPrompt('Proceed? This rewrites timestamps in place and cannot be undone.')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->shift($userId, $days);

        $this->info("Aged {$user->email} by {$days} day(s).");
        $this->line('  The device must FULL-resync (pull-to-refresh) — the shifted rows are older than its cursor.');

        return self::SUCCESS;
    }

    /**
     * `--days` as a positive integer, or null after saying what was wrong.
     *
     * `+3` and `3` both mean «three days have passed». A negative value is refused rather than
     * quietly travelling the other way: this command has no undo, and the shape of a typo
     * (`--days=-3` when you meant `--days=3`) must not be the shape of a silent second meaning.
     */
    private function days(): ?int
    {
        $raw = trim((string) $this->option('days'));
        if ($raw === '') {
            $this->error('--days is required, e.g. --days=+3.');

            return null;
        }

        if (preg_match('/^\+?\d+$/', $raw) !== 1) {
            $this->error("--days must be a positive whole number of days (got «{$raw}»). Travelling backwards is not supported — use qa:reset.");

            return null;
        }

        $days = (int) ltrim($raw, '+');
        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return null;
        }

        return $days;
    }

    /**
     * Every learning table's rows for this account.
     *
     * Printed before the confirmation because the number the operator recognises («I have 12
     * words») is the cheapest check that the right account is about to be rewritten.
     *
     * @return array<string, int>
     */
    private function rowCounts(string $userId): array
    {
        $tables = [
            'user_term_progress',
            'reviews',
            'term_triages',
            'term_exposures',
            'study_sessions',
            'daily_user_stats',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->where('user_id', $userId)->count();
        }

        return $counts;
    }

    /**
     * The shift itself: one transaction, parameterised intervals, no string interpolation into SQL.
     *
     * The column lists are exhaustive on purpose — a learning timestamp left un-shifted is worse
     * than no time travel at all, because the inconsistency shows up several screens later as a
     * bug that is not one. `daily_user_stats.date` is a DATE, not a timestamp, hence its own shape.
     */
    private function shift(string $userId, int $days): void
    {
        DB::transaction(function () use ($userId, $days): void {
            DB::update(
                'UPDATE user_term_progress SET '
                . "due_at = due_at - (? * interval '1 day'), "
                . "last_reviewed_at = last_reviewed_at - (? * interval '1 day'), "
                . "enrolled_at = enrolled_at - (? * interval '1 day'), "
                . "created_at = created_at - (? * interval '1 day'), "
                . "updated_at = updated_at - (? * interval '1 day') "
                . 'WHERE user_id = ?',
                [$days, $days, $days, $days, $days, $userId],
            );

            DB::update(
                'UPDATE reviews SET '
                . "answered_at = answered_at - (? * interval '1 day'), "
                . "created_at = created_at - (? * interval '1 day') "
                . 'WHERE user_id = ?',
                [$days, $days, $userId],
            );

            DB::update(
                'UPDATE term_triages SET '
                . "decided_at = decided_at - (? * interval '1 day'), "
                . "created_at = created_at - (? * interval '1 day') "
                . 'WHERE user_id = ?',
                [$days, $days, $userId],
            );

            DB::update(
                'UPDATE term_exposures SET '
                . "shown_at = shown_at - (? * interval '1 day'), "
                . "created_at = created_at - (? * interval '1 day') "
                . 'WHERE user_id = ?',
                [$days, $days, $userId],
            );

            DB::update(
                'UPDATE study_sessions SET '
                . "started_at = started_at - (? * interval '1 day'), "
                . "ended_at = ended_at - (? * interval '1 day'), "
                . "created_at = created_at - (? * interval '1 day'), "
                . "updated_at = updated_at - (? * interval '1 day') "
                . 'WHERE user_id = ?',
                [$days, $days, $days, $days, $userId],
            );

            $this->shiftDailyStats($userId, $days);
        });
    }

    /**
     * `daily_user_stats` shifts by REBUILDING its rows, not by moving them.
     *
     * A blanket `UPDATE ... SET date = date - interval` collides with the table's own primary key
     * the moment two shifted days would land on one: `duplicate key value violates unique
     * constraint "daily_user_stats_pkey"`, the whole transaction rolls back, and the command
     * reports nothing shifted at all. That is reachable on any account whose history already has a
     * row on the day another row is moving onto — which, walking a QA account forward a day at a
     * time, is most of them after the second walk.
     *
     * Two days landing on one is not a conflict to avoid, though: this table is a PROJECTION of the
     * append-only review log (see the Learning README), and merging its counters is exactly what a
     * replay of those two days would produce. So the rows are read, re-keyed in PHP, summed where
     * they meet, and written back.
     */
    private function shiftDailyStats(string $userId, int $days): void
    {
        $rows = DB::table('daily_user_stats')->where('user_id', $userId)->get();

        /** @var array<string, array{reviews_count: int, new_terms_count: int, correct_count: int, study_seconds: int}> $merged */
        $merged = [];
        foreach ($rows as $row) {
            $date = (new DateTimeImmutable((string) $row->date))
                ->modify('-' . $days . ' days')
                ->format('Y-m-d');

            $merged[$date] ??= ['reviews_count' => 0, 'new_terms_count' => 0, 'correct_count' => 0, 'study_seconds' => 0];
            $merged[$date]['reviews_count'] += (int) $row->reviews_count;
            $merged[$date]['new_terms_count'] += (int) $row->new_terms_count;
            $merged[$date]['correct_count'] += (int) $row->correct_count;
            $merged[$date]['study_seconds'] += (int) $row->study_seconds;
        }

        DB::table('daily_user_stats')->where('user_id', $userId)->delete();

        foreach ($merged as $date => $counts) {
            DB::table('daily_user_stats')->insert(['user_id' => $userId, 'date' => $date, ...$counts]);
        }
    }
}
