<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Port\AdminSignOut;

final class SanctumAdminSignOut implements AdminSignOut
{
    public function revokeCurrent(): void
    {
        $admin = auth('admin')->user();
        if ($admin instanceof Admin) {
            $admin->currentAccessToken()->delete();
        }
    }
}
