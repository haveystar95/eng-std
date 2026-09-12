<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Dto\NotificationRecord;
use App\Modules\Plan\Application\Port\NotificationLog;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/** `plan_notifications` — INSERT … ON CONFLICT DO NOTHING and SELECTs; no UPDATE, no DELETE. */
final class EloquentNotificationLog implements NotificationLog
{
    public function record(NotificationRecord $record): bool
    {
        return DB::table('plan_notifications')->insertOrIgnore([
            'id' => $record->id,
            'user_id' => $record->userId,
            'plan_id' => $record->planId,
            'event_id' => $record->eventId,
            'kind' => $record->kind->value,
            'day_number' => $record->dayNumber,
            'local_date' => $record->localDate,
            'status' => $record->status->value,
            'reason' => $record->reason === null ? null : mb_substr($record->reason, 0, 1000),
        ]) === 1;
    }

    public function hasDailyReminder(UserId $user, string $localDate): bool
    {
        return DB::table('plan_notifications')
            ->where('user_id', $user->value)
            ->where('local_date', $localDate)
            ->where('kind', NotificationKind::DailyReminder->value)
            ->exists();
    }
}
