<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** What the notification tick walks: every started, unfinished plan (stored `active`; `overdue` is derived from it). */
interface NotifiablePlans
{
    /** @return list<PlanId> */
    public function activePlanIds(): array;
}
