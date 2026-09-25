<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

/**
 * A plan beyond the free one, asked for without a subscription (наряд ACC-1 §2): the free plan is the learner's first —
 * a deleted one included, so deleting it does not buy a second. Answered only while the paywall is switched on.
 */
final class PlanSubscriptionRequired extends PlanProblem
{
    public static function afterFree(int $plansEver): self
    {
        return new self("A plan beyond the free one needs a subscription ({$plansEver} made already).", ['plans' => $plansEver]);
    }

    public function problemStatus(): int
    {
        return 402;
    }

    public function problemCode(): string
    {
        return 'plan_subscription_required';
    }

    public function problemTitle(): string
    {
        return 'A subscription is required for another plan';
    }
}
