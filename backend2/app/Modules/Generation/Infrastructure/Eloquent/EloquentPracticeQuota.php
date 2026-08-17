<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\PracticeQuota;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentPracticeQuota implements PracticeQuota
{
    public function usedOn(UserId $userId, DateTimeImmutable $day): int
    {
        $start = $day->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $end = $start->modify('+1 day');

        // Every dialog started that day counts, whatever its status — a start consumes the allowance.
        // A dialog that expires still counted for its own day; the next day is a fresh window.
        return DB::table('practice_dialogs')
            ->where('user_id', $userId->value)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }
}
