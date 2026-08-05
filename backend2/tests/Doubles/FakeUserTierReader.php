<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class FakeUserTierReader implements UserTierReader
{
    public function __construct(private readonly SubscriptionTier $tier = SubscriptionTier::Free) {}

    public function tierOf(UserId $userId): SubscriptionTier
    {
        return $this->tier;
    }
}
