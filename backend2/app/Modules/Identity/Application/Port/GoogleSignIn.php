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
     * @param  string|null  $nativeLanguage  the device's own language when it is one a plan may be read in
     *                                        (`LanguageRoles::planNativeFromLocales`, наряд LANG-1 §7). It
     *                                        seeds `profiles.native_language` ONLY when this sign-in creates
     *                                        the profile — a learner's choice is never overwritten by the
     *                                        phone's settings. Null leaves the column's default (`ru`).
     *
     * @throws InvalidGoogleToken when the id token cannot be verified
     */
    public function authenticate(string $idToken, string $deviceName, ?string $timezone = null, ?string $nativeLanguage = null): AuthResult;
}
