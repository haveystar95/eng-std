<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Port\NotifiablePlans;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use Illuminate\Support\Facades\DB;

/**
 * Every stored-`active` plan. The predicate is exactly the one of the partial unique index
 * `plans_one_active_uidx (user_id) WHERE status = 'active'`, so the scan walks that small index
 * rather than the table.
 */
final class EloquentNotifiablePlans implements NotifiablePlans
{
    public function activePlanIds(): array
    {
        return array_values(DB::table('plans')
            ->where('status', PlanStatus::Active->value)
            ->pluck('id')
            ->map(static fn (mixed $id): PlanId => PlanId::fromString((string) $id))
            ->all());
    }
}
