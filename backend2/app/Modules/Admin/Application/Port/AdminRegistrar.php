<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

/** Creates a back-office admin. Null when an admin with that email already exists. */
interface AdminRegistrar
{
    public function create(string $email, string $name, string $password): ?string;
}
