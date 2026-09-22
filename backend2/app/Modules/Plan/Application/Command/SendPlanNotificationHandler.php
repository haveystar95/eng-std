<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\DeliveryResult;
use App\Modules\Plan\Application\Dto\NotificationRecord;
use App\Modules\Plan\Application\Dto\PushMessage;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\LearnerDevices;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\NotificationLog;
use App\Modules\Plan\Application\Port\PushSender;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\PlanDayNotFound;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\NotificationRules;
use App\Modules\Plan\Domain\Service\NotificationTexts;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\NotificationText;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;

/**
 * THE LETTER, SENT AND LOGGED. Runs in the queue ({@see \App\Modules\Plan\Infrastructure\Job\SendPlanNotificationJob}).
 *
 * The plan is read fresh — the words describe the plan as it is when the letter leaves, and a plan
 * deleted or finished since the fact was written gets no letter and no log line. The daily
 * reminder is checked against the log once more right before sending («не чаще раза в сутки»);
 * the partial unique index on (user, local_date) is the last word if two jobs still race.
 *
 * Every send that happened is logged with its outcome — `not_sent` in dry mode, `no_token` when the
 * key is there and the learner has no address, `sent` / `failed` from APNs.
 */
final readonly class SendPlanNotificationHandler
{
    public function __construct(
        private PlanRepository $plans,
        private LearnerCalendar $calendar,
        private LearnerDevices $devices,
        private PushSender $sender,
        private NotificationLog $log,
        private Clock $clock,
        private LearnerGender $learners,
    ) {}

    /** @return DeliveryResult|null the outcome, or null when no letter was due */
    public function __invoke(SendPlanNotification $command): ?DeliveryResult
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null || ! $plan->userId()->equals($command->userId)) {
            return null;
        }
        $today = $this->calendar->todayFor($command->userId, $this->clock->now());
        if (! NotificationRules::allows($command->kind, $plan->effectiveStatus($today))) {
            return null;
        }
        if ($command->kind === NotificationKind::DailyReminder && $this->log->hasDailyReminder($command->userId, $command->localDate)) {
            return null;
        }

        $text = $this->text($plan, $command, $today);
        if ($text === null) {
            return null;
        }

        $message = new PushMessage(
            userId: $command->userId->value,
            kind: $command->kind,
            title: $text->title,
            body: $text->body,
            planId: $plan->id()->value,
            dayNumber: $command->dayNumber,
        );
        $result = $this->sender->send($message, $this->devices->targetsFor($command->userId));

        $this->log->record(new NotificationRecord(
            id: Ulid::generate(),
            userId: $command->userId->value,
            planId: $plan->id()->value,
            eventId: $command->eventId,
            kind: $command->kind,
            dayNumber: $command->dayNumber,
            localDate: $command->localDate,
            status: $result->status,
            reason: $result->reason,
        ));

        return $result;
    }

    private function text(Plan $plan, SendPlanNotification $command, DateTimeImmutable $today): ?NotificationText
    {
        $texts = new NotificationTexts($plan->nativeLang()->value, $this->learners->of($plan->userId()));

        return match ($command->kind) {
            NotificationKind::PlanReady => $texts->planReady($this->untilPhrase($plan, $today), $plan->daysTotal(), $this->dayTitle($plan, 1, $texts) ?? ''),
            NotificationKind::DayReady => $this->dayLetter($plan, $command, $texts, static fn (int $n, string $t): NotificationText => $texts->dayReady($n, $t)),
            NotificationKind::DailyReminder => $this->dayLetter($plan, $command, $texts, static fn (int $n, string $t): NotificationText => $texts->dailyReminder($n, $t)),
            NotificationKind::EventToday => $texts->eventToday($plan->titles()?->eventNative),
            NotificationKind::DaysSkippedRebuilt => $command->from !== null && $command->to !== null
                ? $texts->daysSkippedRebuilt($command->from, $command->to)
                : null,
        };
    }

    /** @param callable(int, string): NotificationText $make */
    private function dayLetter(Plan $plan, SendPlanNotification $command, NotificationTexts $texts, callable $make): ?NotificationText
    {
        if ($command->dayNumber === null) {
            return null;
        }
        $title = $this->dayTitle($plan, $command->dayNumber, $texts);

        return $title === null ? null : $make($command->dayNumber, $title);
    }

    private function dayTitle(Plan $plan, int $number, NotificationTexts $texts): ?string
    {
        try {
            $day = $plan->day($number);
        } catch (PlanDayNotFound) {
            return null; // the route was shortened past this day since the fact was written
        }

        return $texts->dayTitle($day->type(), $plan->sceneOf($day)?->titleNative());
    }

    /** The same countdown the plan screen prints («До приёма · 7 дней»), or null without a date. */
    private function untilPhrase(Plan $plan, DateTimeImmutable $today): ?string
    {
        $titles = $plan->titles();
        $daysLeft = $plan->daysLeftUntilEvent($today);
        if ($titles === null || $daysLeft === null || $daysLeft < 0) {
            return null;
        }

        return (new NativeStrings($plan->nativeLang()->value))->untilPhrase($titles->untilPhraseNative, $daysLeft);
    }
}
