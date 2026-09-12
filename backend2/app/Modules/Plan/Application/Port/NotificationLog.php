<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\NotificationRecord;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The delivery log (`plan_notifications`) — append and read. No update, no delete: the eraser and
 * the plan FK cascade are the only ways out.
 */
interface NotificationLog
{
    /** False when the schema refused it: a second daily reminder for the same local date. */
    public function record(NotificationRecord $record): bool;

    /** Has the learner already had a daily reminder on this local date (`Y-m-d`)? */
    public function hasDailyReminder(UserId $user, string $localDate): bool;
}
