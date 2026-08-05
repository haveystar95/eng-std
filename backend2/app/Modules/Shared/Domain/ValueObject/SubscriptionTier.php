<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * A user's subscription plan. Cross-cutting: Identity owns the stored value (profiles.tier),
 * Generation reads it for the daily quota, Collections reads it to gate premium store content —
 * so it lives in Shared like the other ids/enums referenced across modules.
 *
 * StoreKit receipt validation flips this later; for now it is set out-of-band and defaults to free.
 */
enum SubscriptionTier: string
{
    case Free = 'free';
    case Premium = 'premium';

    public function isPremium(): bool
    {
        return $this === self::Premium;
    }
}
