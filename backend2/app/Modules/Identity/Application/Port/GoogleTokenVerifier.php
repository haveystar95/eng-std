<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\Dto\GoogleIdentity;

/**
 * Verifies a Google ID token (minted by native Google Sign-In on the device) and returns
 * its trusted claims, or null if the token is invalid/expired/for the wrong audience.
 * The real implementation talks to Google's libraries; tests fake it.
 */
interface GoogleTokenVerifier
{
    public function verify(string $idToken): ?GoogleIdentity;
}
