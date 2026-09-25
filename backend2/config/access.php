<?php

declare(strict_types=1);

return [

    /*
     * THE PAYWALL OF THE PLAN (наряд ACC-1 §2) — «один план, день 1 бесплатно».
     *
     * On: a learner without a subscription has ONE plan — the first they ever made, a deleted one included — whose day 1
     * is open whole, the talk with it, and every later day locked (`days[].lock_reason: subscription`); any further plan
     * is 402 `plan_subscription_required`. A subscription in force (`entitlements`: `active` or `grace`, no end or an end
     * ahead) lifts it all, and a subscriber has at most `open_plans_cap` plans in work at once — the next is 409
     * `plan_active_limit`.
     *
     * Off: nothing is locked by a subscription and no plan is refused — the days and `POST /plans` are what they were
     * before the наряд — while `GET /auth/me` → `access`, the table and `access:grant` / `access:revoke` work all the same.
     *
     * OFF ON THE STAND until the client's paywall наряд. A worker reads it at its start: after changing it,
     * `docker compose restart horizon scheduler`.
     */
    'paywall_enabled' => (bool) env('ACCESS_PAYWALL_ENABLED', false),

    'open_plans_cap' => (int) env('ACCESS_OPEN_PLANS_CAP', 3),

];
