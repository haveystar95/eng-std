<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * Where a stage of a day on the route stands: walked, the one to walk now, or not yet. Exactly
 * three words — the route never carries a stage the day does not have, so there is no `absent`
 * here (the day room's own progress list keeps that word for its five fixed rows).
 */
enum StageState: string
{
    case Done = 'done';
    case Current = 'current';
    case Locked = 'locked';
}
