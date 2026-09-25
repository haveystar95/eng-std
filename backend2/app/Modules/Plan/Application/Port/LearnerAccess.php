<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * DOES THE LEARNER HAVE A SUBSCRIPTION NOW (наряд ACC-1 §2) — the one fact of Identity the plan's paywall asks: a right
 * to the paid plan in force (status `active` or `grace`, no end or an end still ahead). Asked only while the paywall is
 * switched on.
 */
interface LearnerAccess
{
    public function subscribed(UserId $user): bool;
}
