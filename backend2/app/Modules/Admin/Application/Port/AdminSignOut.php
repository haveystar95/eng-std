<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

/** Revokes the admin's current bearer token (this device only). */
interface AdminSignOut
{
    public function revokeCurrent(): void;
}
