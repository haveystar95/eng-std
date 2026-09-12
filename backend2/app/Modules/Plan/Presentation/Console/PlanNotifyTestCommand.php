<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Console;

use App\Modules\Plan\Application\Command\SendPlanNotification;
use App\Modules\Plan\Application\Command\SendPlanNotificationHandler;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Query\GetCurrentPlan;
use App\Modules\Plan\Application\Query\GetCurrentPlanHandler;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Console\Command;

/**
 * QA: send one letter about the learner's current plan NOW, through the same handler the queue
 * runs — words, addresses, sender (dry or APNs) and the delivery log. The log line is real: a test
 * `daily_reminder` spends that day's one reminder.
 */
final class PlanNotifyTestCommand extends Command
{
    protected $signature = 'plan:notify-test {user : user id} {kind : plan_ready|day_ready|daily_reminder|event_today|days_skipped_rebuilt}'
        .' {--day= : day number for day_ready / daily_reminder (default: the current day)} {--from=7} {--to=5}';

    protected $description = 'QA: send one plan notification to a user now (dry mode logs it)';

    public function handle(GetCurrentPlanHandler $current, SendPlanNotificationHandler $send, LearnerCalendar $calendar, Clock $clock): int
    {
        $kindArg = $this->argument('kind');
        $kind = NotificationKind::tryFrom(is_string($kindArg) ? $kindArg : '');
        if ($kind === null) {
            $this->error('Unknown kind. One of: '.implode(', ', array_map(static fn (NotificationKind $k): string => $k->value, NotificationKind::cases())));

            return self::FAILURE;
        }
        $userArg = $this->argument('user');
        $user = UserId::fromString(is_string($userArg) ? $userArg : '');
        $plan = $current(new GetCurrentPlan($user));
        if ($plan === null) {
            $this->error('The user has no current plan.');

            return self::FAILURE;
        }

        $day = $this->option('day') !== null ? (int) $this->option('day') : ($plan->currentDay->number ?? 1);
        $result = $send(new SendPlanNotification(
            userId: $user,
            planId: PlanId::fromString($plan->id),
            kind: $kind,
            localDate: $calendar->todayFor($user, $clock->now())->format('Y-m-d'),
            dayNumber: in_array($kind, [NotificationKind::DayReady, NotificationKind::DailyReminder], true) ? $day : null,
            from: (int) $this->option('from'),
            to: (int) $this->option('to'),
        ));

        if ($result === null) {
            $this->warn('No letter: not due for this plan (status, or today\'s reminder already logged).');

            return self::SUCCESS;
        }
        $this->info("{$kind->value}: {$result->status->value}".($result->reason !== null ? " — {$result->reason}" : ''));

        return self::SUCCESS;
    }
}
