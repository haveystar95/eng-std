<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Flip an app user's subscription tier (the one v1 admin mutation). */
final readonly class ChangeUserTier
{
    public function __construct(
        public string $adminId,
        public UserId $userId,
        public SubscriptionTier $tier,
    ) {}
}
