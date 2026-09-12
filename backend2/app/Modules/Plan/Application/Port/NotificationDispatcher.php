<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Command\SendPlanNotification;

/** Puts a letter on the queue (`SendPlanNotificationJob`) — the sender never runs inside a learner's request. */
interface NotificationDispatcher
{
    public function dispatch(SendPlanNotification $command): void;
}
