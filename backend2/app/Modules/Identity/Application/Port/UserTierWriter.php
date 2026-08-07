<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;

/**
 * Sets a user's subscription tier out-of-band. Today only the dev `practice:grant-premium` command
 * uses it (for self-testing premium features); StoreKit receipt validation becomes a second caller
 * later. The write side of {@see UserTierReader}.
 */
interface UserTierWriter
{
    /** Set the tier for the user with this email. False when no such user (with a profile) exists. */
    public function setTierByEmail(string $email, SubscriptionTier $tier): bool;
}
