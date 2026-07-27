<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Port\SignOut;
use App\Modules\Identity\Infrastructure\Eloquent\User;

final class SanctumSignOut implements SignOut
{
    public function revokeCurrent(): void
    {
        $user = auth()->user();
        if ($user instanceof User) {
            $user->currentAccessToken()->delete();
        }
    }
}
