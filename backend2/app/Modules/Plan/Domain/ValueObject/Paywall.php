<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Entity\PlanDay;

/**
 * THE PAYWALL AS ONE PLAN OF ONE LEARNER MEETS IT (наряд ACC-1 §2) — «один план, день 1 бесплатно».
 *
 * Open — the paywall is switched off (`access.paywall_enabled`, off on the stand until the client's paywall), or the
 * learner has a subscription: nothing is locked by it. Closed — the learner has none: the FREE plan (the learner's first
 * plan by `created_at`, a deleted one included) has its day 1 open whole, the talk with it, and every day after it
 * locked; any other plan has every day locked.
 *
 * It locks what is still to be opened, never what is being walked or is walked: a day in progress when a subscription
 * ran out is finished, a closed day keeps its «Ещё раз» — the next day is the one that waits. The aggregate asks it
 * where a day opens ({@see \App\Modules\Plan\Domain\Entity\Plan::openDay()}) and where its status is read
 * ({@see \App\Modules\Plan\Domain\Entity\Plan::effectiveDayStatus()}), so the route, the room, the reminder and the
 * door of the day read one rule.
 */
final readonly class Paywall
{
    private function __construct(
        private bool $closed,
        private bool $freePlan,
    ) {}

    /** Nothing is locked by the subscription: the switch is off, or the learner has one. */
    public static function open(): self
    {
        return new self(false, false);
    }

    /** The switch is on and the learner has no subscription; `$freePlan` — this plan is the learner's free one. */
    public static function withoutSubscription(bool $freePlan): self
    {
        return new self(true, $freePlan);
    }

    /** Does the paywall hold this day shut — a day still to be opened, past the free plan's day 1. */
    public function locks(PlanDay $day): bool
    {
        if (! $this->closed || ($this->freePlan && $day->number() === 1)) {
            return false;
        }

        return $day->status() !== DayStatus::InProgress && $day->status() !== DayStatus::Closed;
    }
}
