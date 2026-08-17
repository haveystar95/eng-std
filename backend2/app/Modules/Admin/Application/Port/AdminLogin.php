<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\AdminSession;

/** Verifies admin credentials and mints a bearer token. Null when the credentials don't match. */
interface AdminLogin
{
    public function attempt(string $email, string $password, string $deviceName): ?AdminSession;
}
