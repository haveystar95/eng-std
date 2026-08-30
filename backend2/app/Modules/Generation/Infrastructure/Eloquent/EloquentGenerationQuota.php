<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentGenerationQuota implements GenerationQuota
{
    public function usedOn(UserId $userId, DateTimeImmutable $day): int
    {
        $start = $day->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $end = $start->modify('+1 day');

        // Failed requests don't count — a failure refunds the user's daily allowance.
        $generations = DB::table('generation_requests')
            ->where('user_id', $userId->value)
            // The allowance is «сколько коллекций я могу создать за день». A learning plan spends
            // money in the same ledger and is not that act — it has its own limit (one active plan)
            // and its own budget. Counting it here would silently shrink the collection allowance
            // by however many days a plan happens to have.
            ->where('purpose', 'generation')
            ->where('status', '<>', 'failed')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();

        // A "New example" regeneration counts as a generation against the same daily allowance.
        $regenerations = DB::table('example_regenerations')
            ->where('user_id', $userId->value)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();

        return $generations + $regenerations;
    }
}
