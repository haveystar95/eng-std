<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Port\PlanAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/** Deleting the plans cascades through scenes, days, cards, terms and audio rows by FK. */
final class EloquentPlanAccountEraser implements PlanAccountEraser
{
    public function eraseFor(UserId $userId): void
    {
        DB::table('plans')->where('user_id', $userId->value)->delete();
    }
}
