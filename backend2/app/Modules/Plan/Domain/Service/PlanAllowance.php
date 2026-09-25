<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Exception\PlanActiveLimit;
use App\Modules\Plan\Domain\Exception\PlanSubscriptionRequired;

/**
 * MAY THE LEARNER HAVE ANOTHER PLAN (наряд ACC-1 §2), asked by `POST /plans` while the paywall is switched on:
 *
 * - without a subscription — one plan, the first; any plan after it (the first deleted or not) is 402
 *   `plan_subscription_required`;
 * - with one — up to {@see OPEN_PLANS_CAP} plans in work at once (not finished, not deleted); the next is 409
 *   `plan_active_limit`.
 *
 * «Active» of the order is «in work» here, not the status `active`: a learner has at most ONE started plan by the rule
 * «one live plan» (`plans_one_active_uidx`, 409 `plan_already_active` on «Начать»), so a cap of three can only count the
 * plans a learner has in the menu — being built, ready to start, going.
 */
final class PlanAllowance
{
    public const OPEN_PLANS_CAP = 3;

    public static function assertMayCreate(bool $subscribed, int $plansEver, int $plansInWork, int $cap = self::OPEN_PLANS_CAP): void
    {
        if (! $subscribed) {
            if ($plansEver > 0) {
                throw PlanSubscriptionRequired::afterFree($plansEver);
            }

            return;
        }
        if ($plansInWork >= $cap) {
            throw PlanActiveLimit::at($cap, $plansInWork);
        }
    }
}
