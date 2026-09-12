<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Port\PlanAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/**
 * Deleting the plans cascades through scenes, days, cards, terms and audio rows by FK — and through
 * the journal (`plan_events`) and the delivery log (`plan_notifications`). Those two are also
 * deleted by `user_id` first, explicitly: they are the append-only tables, the eraser is the one
 * path allowed to remove their rows, and it should not depend on a cascade to say so.
 */
final class EloquentPlanAccountEraser implements PlanAccountEraser
{
    public function eraseFor(UserId $userId): void
    {
        DB::table('plan_notifications')->where('user_id', $userId->value)->delete();
        DB::table('plan_events')->where('user_id', $userId->value)->delete();
        DB::table('plans')->where('user_id', $userId->value)->delete();
    }
}
