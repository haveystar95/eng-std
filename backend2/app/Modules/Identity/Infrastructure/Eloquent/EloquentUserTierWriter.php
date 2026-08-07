<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Port\UserTierWriter;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;

final class EloquentUserTierWriter implements UserTierWriter
{
    public function setTierByEmail(string $email, SubscriptionTier $tier): bool
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return false;
        }

        // tier lives on the profile row; a user without a profile has nowhere to store it yet.
        $affected = Profile::query()->where('user_id', $user->id)->update(['tier' => $tier->value]);

        return $affected > 0;
    }
}
