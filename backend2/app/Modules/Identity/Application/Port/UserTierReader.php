<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The user's subscription tier — the one cross-module fact other modules need about a plan.
 * Identity owns the stored value (profiles.tier); Generation reads it for the daily quota and
 * Collections reads it to gate premium content, both through this Application port.
 */
interface UserTierReader
{
    /** Free for an unknown user or a profile without a tier — the safe default (no premium access). */
    public function tierOf(UserId $userId): SubscriptionTier;
}
