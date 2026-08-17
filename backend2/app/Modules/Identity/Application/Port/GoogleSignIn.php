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
    /**
     * @param  string|null  $timezone  the device's IANA timezone; on first sign-in it seeds the new
     *                                  profile so calendar-day due rounding (F19) works before the
     *                                  client's first `PUT /profile`. Null leaves the UTC fallback.
     *
     * @throws InvalidGoogleToken when the id token cannot be verified
     */
    public function authenticate(string $idToken, string $deviceName, ?string $timezone = null): AuthResult;
}
