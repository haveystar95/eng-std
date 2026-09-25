<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\LearnerAccess;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\PlanAllowance;
use App\Modules\Plan\Domain\ValueObject\Paywall;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «ОДИН ПЛАН, ДЕНЬ 1 БЕСПЛАТНО» (наряд ACC-1 §2) — the paywall as the plan meets it, put together from the switch, the
 * learner's subscription (Identity, through {@see LearnerAccess}) and the learner's plans.
 *
 * THE SWITCH (`access.paywall_enabled`, env `ACCESS_PAYWALL_ENABLED`) is off on the stand until the client's paywall
 * наряд: off, nothing is locked by a subscription and no plan is refused — the days and `POST /plans` are exactly as
 * before the наряд — while `GET /auth/me` → `access` and the table work all the same. Nothing is asked of Identity or
 * of the plans while it is off. It is read where the provider builds this class; a worker holds it from its start —
 * flipping it takes `docker compose restart horizon scheduler`.
 */
final readonly class Paywalls
{
    public function __construct(
        private LearnerAccess $access,
        private PlanRepository $plans,
        private bool $enabled,
        private int $openPlansCap = PlanAllowance::OPEN_PLANS_CAP,
    ) {}

    /** The paywall this plan's days are locked by — open while the switch is off or the learner has a subscription. */
    public function of(Plan $plan): Paywall
    {
        if (! $this->enabled || $this->access->subscribed($plan->userId())) {
            return Paywall::open();
        }

        return Paywall::withoutSubscription($this->plans->freePlanIdOf($plan->userId())?->equals($plan->id()) === true);
    }

    /**
     * May the learner have another plan — asked by `POST /plans` inside the transaction that writes it, the learner's
     * plans locked ({@see PlanRepository::lockPlansOf()}). Throws 402 `plan_subscription_required` / 409
     * `plan_active_limit` ({@see PlanAllowance}); says nothing while the switch is off.
     */
    public function assertMayCreate(UserId $learner): void
    {
        if (! $this->enabled) {
            return;
        }
        $this->plans->lockPlansOf($learner);
        $subscribed = $this->access->subscribed($learner);
        PlanAllowance::assertMayCreate(
            $subscribed,
            $subscribed ? 0 : $this->plans->countOf($learner),
            $subscribed ? $this->plans->countInWorkOf($learner) : 0,
            $this->openPlansCap,
        );
    }
}
