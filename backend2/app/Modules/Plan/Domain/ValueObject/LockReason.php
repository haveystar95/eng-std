<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHY A DAY IS LOCKED (наряд ACC-1 §2, `days[].lock_reason`): `date` — the lock there always was: its calendar day has
 * not come, or the day before it is not closed; `subscription` — the paywall: past day 1 of the learner's free plan, or
 * any day of another plan, while the learner has no subscription and the paywall is switched on. A day that is not
 * locked has no reason (null).
 */
enum LockReason: string
{
    case Date = 'date';
    case Subscription = 'subscription';
}
