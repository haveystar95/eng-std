<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\Dto\AuthResult;
use App\Modules\Identity\Domain\Exception\InvalidGoogleToken;

/**
 * The sign-in use case: verify the Google token, upsert the user and their profile, and
 * issue a per-device Sanctum token. Implemented in Infrastructure because it touches the
 * Eloquent user and Sanctum — the controller only depends on this port.
 */
interface GoogleSignIn
{
    /** @throws InvalidGoogleToken when the id token cannot be verified */
    public function authenticate(string $idToken, string $deviceName): AuthResult;
}
