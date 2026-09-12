<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** The letters the plan sends (`plan_notifications.kind`). */
enum NotificationKind: string
{
    case PlanReady = 'plan_ready';
    case DayReady = 'day_ready';
    case DailyReminder = 'daily_reminder';
    case EventToday = 'event_today';
    case DaysSkippedRebuilt = 'days_skipped_rebuilt';
}
