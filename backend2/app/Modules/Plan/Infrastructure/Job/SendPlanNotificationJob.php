<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Plan\Application\Command\SendPlanNotification;
use App\Modules\Plan\Application\Command\SendPlanNotificationHandler;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Infrastructure\Adapter\IdentityLearnerCalendar;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One letter to one learner. One try: a letter retried by the queue is a letter the learner may get
 * twice, and a failed delivery is already logged as `failed` by the handler.
 */
final class SendPlanNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private readonly string $userId,
        private readonly string $planId,
        private readonly string $kind,
        private readonly string $localDate,
        private readonly ?int $dayNumber = null,
        private readonly ?string $eventId = null,
        private readonly ?int $from = null,
        private readonly ?int $to = null,
    ) {}

    public function handle(): void
    {
        // The calendar adapter memoises the learner's zone for a request; a worker lives for days,
        // and a zone changed in between must not be read from a previous job's memo.
        app()->forgetInstance(IdentityLearnerCalendar::class);

        app(SendPlanNotificationHandler::class)(new SendPlanNotification(
            userId: UserId::fromString($this->userId),
            planId: PlanId::fromString($this->planId),
            kind: NotificationKind::from($this->kind),
            localDate: $this->localDate,
            dayNumber: $this->dayNumber,
            eventId: $this->eventId,
            from: $this->from,
            to: $this->to,
        ));
    }

    public function failed(Throwable $e): void
    {
        Log::error('SendPlanNotificationJob failed', ['user_id' => $this->userId, 'plan_id' => $this->planId, 'kind' => $this->kind, 'error' => $e->getMessage()]);
    }
}
