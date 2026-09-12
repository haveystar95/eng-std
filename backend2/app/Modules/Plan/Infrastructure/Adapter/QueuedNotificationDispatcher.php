<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Command\SendPlanNotification;
use App\Modules\Plan\Application\Port\NotificationDispatcher;
use App\Modules\Plan\Infrastructure\Job\SendPlanNotificationJob;

final class QueuedNotificationDispatcher implements NotificationDispatcher
{
    public function dispatch(SendPlanNotification $command): void
    {
        SendPlanNotificationJob::dispatch(
            $command->userId->value,
            $command->planId->value,
            $command->kind->value,
            $command->localDate,
            $command->dayNumber,
            $command->eventId,
            $command->from,
            $command->to,
        );
    }
}
