<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * `locked` — not yet available (the previous day is open, or the calendar has not turned);
 * `open` — may be started today; `in_progress` — cards dealt, the learner is inside;
 * `closed` — walked and closed, metrics written.
 */
enum DayStatus: string
{
    case Locked = 'locked';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';
}
