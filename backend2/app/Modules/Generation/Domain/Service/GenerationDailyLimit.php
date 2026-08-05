<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;

/**
 * How many collections a user may generate per UTC day, by plan. The gate is the tier; the
 * numbers live here (one place) so the request handler and the /me quota view agree on the limit.
 */
final class GenerationDailyLimit
{
    public const FREE = 3;

    public const PREMIUM = 20;

    public function forTier(SubscriptionTier $tier): int
    {
        return $tier->isPremium() ? self::PREMIUM : self::FREE;
    }
}
