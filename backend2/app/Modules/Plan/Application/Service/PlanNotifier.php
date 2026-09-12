<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Command\SendPlanNotification;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\NotificationDispatcher;
use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Service\NotificationRules;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Turns a journal line (or the tick's daily reminder) into a queued letter — one job per letter,
 * per user. Which lines become letters is {@see NotificationRules::forEvent()}; the words, the
 * addresses and the delivery are the job's ({@see \App\Modules\Plan\Application\Command\SendPlanNotificationHandler}).
 *
 * Called after the transaction that wrote the line: a job that ran before the commit would read a
 * plan that does not have the change yet.
 */
final readonly class PlanNotifier
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private LearnerCalendar $calendar,
        private Clock $clock,
    ) {}

    public function notify(?PlanEvent $event): void
    {
        if ($event === null) {
            return;
        }
        $kind = NotificationRules::forEvent($event->kind, $event->dayNumber);
        if ($kind === null) {
            return;
        }

        $from = $event->payload['from'] ?? null;
        $to = $event->payload['to'] ?? null;
        $this->dispatcher->dispatch(new SendPlanNotification(
            userId: $event->userId,
            planId: $event->planId,
            kind: $kind,
            localDate: $this->localDate($event->userId),
            dayNumber: $event->dayNumber,
            eventId: $event->id->value,
            from: is_int($from) ? $from : null,
            to: is_int($to) ? $to : null,
        ));
    }

    /** The daily reminder for the day that is waiting, stamped with the learner's local date the tick checked. */
    public function remindDaily(UserId $userId, PlanId $planId, int $dayNumber, DateTimeImmutable $localToday): void
    {
        $this->dispatcher->dispatch(new SendPlanNotification(
            userId: $userId,
            planId: $planId,
            kind: NotificationKind::DailyReminder,
            localDate: $localToday->format('Y-m-d'),
            dayNumber: $dayNumber,
        ));
    }

    private function localDate(UserId $user): string
    {
        return $this->calendar->todayFor($user, $this->clock->now())->format('Y-m-d');
    }
}
