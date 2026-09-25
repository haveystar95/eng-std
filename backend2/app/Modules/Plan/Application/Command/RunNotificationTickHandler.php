<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\LearnerHabits;
use App\Modules\Plan\Application\Port\NotifiablePlans;
use App\Modules\Plan\Application\Port\NotificationLog;
use App\Modules\Plan\Application\Service\PlanEventJournal;
use App\Modules\Plan\Application\Service\PlanNotifier;
use App\Modules\Plan\Application\Service\Paywalls;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\NotificationRules;
use App\Modules\Plan\Domain\Service\PlanEventRules;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Service\Clock;
use DateTimeImmutable;

/**
 * THE TICK. For every active plan, in the learner's own zone:
 *
 * 1. the event date — `event_today` on the day at the reminder hour, `event_passed`
 *    after it; each written once per plan (checked first, and the partial unique index holds it if
 *    two ticks overlap), `event_today` becomes a letter;
 * 2. the daily reminder — when now is inside [reminder hour, +15 min), the current day is
 *    available and not closed, the learner has had no reminder on this local date, and today is not
 *    the event day (that day's one letter is «Сегодня разговор»).
 *
 * The reminder hour is ONE rule — Identity's `UsualVisitTime` (the usual visit hour, never before
 * 08:00) — read once per plan here and handed to the phone in `GET /plans` as `reminder_hour`.
 *
 * Idempotent by construction: a second run in the same quarter hour finds the journal lines and the
 * log line already there and does nothing. Reads only; every write goes through the journal or the
 * letter job.
 */
final readonly class RunNotificationTickHandler
{
    public function __construct(
        private NotifiablePlans $live,
        private PlanRepository $plans,
        private PlanEventRepository $events,
        private PlanEventJournal $journal,
        private PlanNotifier $notifier,
        private NotificationLog $log,
        private LearnerCalendar $calendar,
        private LearnerHabits $habits,
        private Clock $clock,
        private Paywalls $paywalls,
    ) {}

    public function __invoke(RunNotificationTick $command): void
    {
        $now = $this->clock->now();
        foreach ($this->live->activePlanIds() as $planId) {
            $plan = $this->plans->findById($planId);
            if ($plan === null || $plan->status() !== PlanStatus::Active) {
                continue;
            }
            $localNow = $now->setTimezone($this->calendar->timezoneFor($plan->userId()));
            $reminder = $this->habits->usualVisitMinutes($plan->userId());
            $this->calendarFacts($plan, $localNow, $reminder);
            $this->dailyReminder($plan, $localNow, $reminder);
        }
    }

    private function calendarFacts(Plan $plan, DateTimeImmutable $localNow, int $reminderMinutes): void
    {
        $eventDate = $plan->eventDate();
        if ($eventDate === null) {
            return;
        }
        $due = PlanEventRules::dueOnCalendar(
            $eventDate,
            $localNow,
            $reminderMinutes,
            $this->events->has($plan->id(), PlanEventKind::EventToday),
            $this->events->has($plan->id(), PlanEventKind::EventPassed),
        );
        foreach ($due as $kind) {
            $event = $this->journal->record($plan->id(), $plan->userId(), $kind, payload: ['event_date' => $eventDate->format('Y-m-d')]);
            $this->notifier->notify($event);
        }
    }

    private function dailyReminder(Plan $plan, DateTimeImmutable $localNow, int $reminderMinutes): void
    {
        $today = $localNow->setTime(0, 0);
        if ($plan->eventDate()?->format('Y-m-d') === $today->format('Y-m-d')) {
            return;
        }
        $day = $plan->currentDay();
        // «Available» is read with the paywall (наряд ACC-1 §2): a day it holds is not waiting for the learner.
        $waiting = $day !== null && ! $plan->isDayBuilding($day)
            && in_array($plan->effectiveDayStatus($day, $today, $this->paywalls->of($plan)), [DayStatus::Open, DayStatus::InProgress], true);
        if (! $waiting) {
            return;
        }
        $due = NotificationRules::reminderDue(
            $localNow,
            $reminderMinutes,
            true,
            $this->log->hasDailyReminder($plan->userId(), $today->format('Y-m-d')),
        );
        if ($due) {
            $this->notifier->remindDaily($plan->userId(), $plan->id(), $day->number(), $today);
        }
    }
}
