<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DEV time-machine for the device-batch. Shifts a user's SRS state back by N hours so due cards come
 * due *now* without waiting real wall-clock time — lets a day-2 SRS run happen during the day.
 *
 * Local only, opt-in, loud. It rewrites persisted timestamps (`user_term_progress.due_at` /
 * `updated_at` / `last_reviewed_at`, and optionally `reviews.created_at` / `answered_at`), so every
 * application is FORCED-TIME and must be noted in the batch log. The client must do a FULL resync
 * afterwards (pull-to-refresh → `resync()` clears the cursor) to pull the shifted rows.
 *
 * Not wired into any user-facing flow. See docs/device-batch-run1.md (F19 / forced-time notes).
 */
final class BatchAgeProgressCommand extends Command
{
    protected $signature = 'batch:age-progress
        {email : the user whose progress to age}
        {--hours=19 : how many hours to shift everything back}
        {--reviews : also shift reviews.created_at/answered_at (activity/quota consistency)}
        {--force : skip the confirmation prompt (non-interactive runs)}';

    protected $description = 'DEV(local): shift a user\'s SRS progress back by N hours so due cards come due now';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to run outside APP_ENV=local.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $hours = (int) $this->option('hours');
        if ($hours <= 0) {
            $this->error('--hours must be a positive integer.');

            return self::FAILURE;
        }

        $user = DB::table('users')->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        $alsoReviews = (bool) $this->option('reviews');
        $progressRows = DB::table('user_term_progress')->where('user_id', $user->id)->count();
        $reviewRows = DB::table('reviews')->where('user_id', $user->id)->count();

        $this->warn("FORCED-TIME: about to age {$email} back by {$hours}h");
        $this->line("  user_term_progress: {$progressRows} rows (due_at, updated_at, last_reviewed_at)");
        if ($alsoReviews) {
            $this->line("  reviews: {$reviewRows} rows (created_at, answered_at)");
        }

        if (! (bool) $this->option('force') && ! $this->confirm('Proceed? (forced-time, dev only)')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // Parameterised raw UPDATEs: the SQL is a literal string, the hour count binds — no string
        // interpolation into the query (Postgres `N * interval '1 hour'`).
        $userId = (string) $user->id;
        DB::transaction(function () use ($userId, $hours, $alsoReviews): void {
            DB::update(
                "UPDATE user_term_progress SET "
                . "due_at = due_at - (? * interval '1 hour'), "
                . "updated_at = updated_at - (? * interval '1 hour'), "
                . "last_reviewed_at = last_reviewed_at - (? * interval '1 hour') "
                . 'WHERE user_id = ?',
                [$hours, $hours, $hours, $userId],
            );
            if ($alsoReviews) {
                DB::update(
                    "UPDATE reviews SET "
                    . "created_at = created_at - (? * interval '1 hour'), "
                    . "answered_at = answered_at - (? * interval '1 hour') "
                    . 'WHERE user_id = ?',
                    [$hours, $hours, $userId],
                );
            }
        });

        $scope = $alsoReviews ? 'progress + reviews' : 'progress';
        $this->info("Aged {$email} by {$hours} hours ({$scope}). Client must full-resync to pick it up.");

        return self::SUCCESS;
    }
}
