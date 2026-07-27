<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

/** Revokes the bearer token used for the current request (per-device logout). */
interface SignOut
{
    public function revokeCurrent(): void;
}
