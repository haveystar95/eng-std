<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class EloquentUserTierReader implements UserTierReader
{
    public function tierOf(UserId $userId): SubscriptionTier
    {
        $tier = Profile::query()
            ->where('user_id', $userId->value)
            ->value('tier');

        return $tier !== null ? SubscriptionTier::from((string) $tier) : SubscriptionTier::Free;
    }
}
