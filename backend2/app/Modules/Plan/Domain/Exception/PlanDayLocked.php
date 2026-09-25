<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * The day is not available yet: the previous one is still open, or the calendar has not turned (`meta.lock_reason:
 * date`) — or, while the paywall is on, the learner has no subscription for it (`meta.lock_reason: subscription`, наряд
 * ACC-1 §2: past day 1 of the free plan, or a day of another plan).
 */
final class PlanDayLocked extends PlanProblem
{
    public static function behindDay(int $number, int $blockedBy): self
    {
        return new self("Day {$number} is locked by day {$blockedBy}.", ['day' => $number, 'blocked_by_day' => $blockedBy, 'lock_reason' => 'date']);
    }

    public static function untilDate(int $number, string $opensOn): self
    {
        return new self("Day {$number} opens on {$opensOn}.", ['day' => $number, 'opens_on' => $opensOn, 'lock_reason' => 'date']);
    }

    public static function bySubscription(int $number): self
    {
        return new self("Day {$number} needs a subscription.", ['day' => $number, 'lock_reason' => 'subscription']);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_day_locked';
    }

    public function problemTitle(): string
    {
        return 'This day is locked';
    }
}
