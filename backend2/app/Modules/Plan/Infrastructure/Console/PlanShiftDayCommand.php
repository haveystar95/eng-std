<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * THE SIMULATOR'S CALENDAR: move one plan's dates N days into the past, so that «the next
 * calendar day» has come without waiting for it. Data is shifted, not the clock: the rule «day
 * N+1 opens no earlier than the day after N was closed» is then exercised as it stands.
 *
 * Refused on the main database unless `--force`: this is a QA tool, and a plan whose dates were
 * shifted is a plan whose history lies.
 */
final class PlanShiftDayCommand extends Command
{
    protected $signature = 'plan:shift-day {plan : plan id} {--days=1 : how many days into the past} {--force : allow on the main database}';

    protected $description = 'QA: shift a plan\'s calendar N days into the past so the next day may open';

    public function handle(): int
    {
        $planId = is_string($this->argument('plan')) ? $this->argument('plan') : '';
        $days = max(1, (int) $this->option('days'));
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database === 'wordtrainer' && ! $this->option('force')) {
            $this->error('Refusing on the main database; pass --force if this is really what you want.');

            return self::FAILURE;
        }
        if (DB::table('plans')->where('id', $planId)->doesntExist()) {
            $this->error("No plan {$planId}.");

            return self::FAILURE;
        }

        $interval = "{$days} days";
        DB::transaction(function () use ($planId, $interval): void {
            DB::statement(
                'UPDATE plans SET created_at = created_at - ?::interval, started_at = started_at - ?::interval, '
                .'finished_at = finished_at - ?::interval, event_date = event_date - ?::interval WHERE id = ?',
                [$interval, $interval, $interval, $interval, $planId],
            );
            DB::statement(
                'UPDATE plan_days SET opens_on = opens_on - ?::interval, opened_at = opened_at - ?::interval, '
                .'closed_at = closed_at - ?::interval WHERE plan_id = ?',
                [$interval, $interval, $interval, $planId],
            );
            DB::statement(
                'UPDATE day_cards SET answered_at = answered_at - ?::interval WHERE day_id IN (SELECT id FROM plan_days WHERE plan_id = ?)',
                [$interval, $planId],
            );
        });

        $this->info("Plan {$planId} shifted {$days} day(s) into the past.");

        return self::SUCCESS;
    }
}
