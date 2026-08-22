<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\Dto\AuthResult;
use App\Modules\Identity\Domain\Exception\DevLoginUnavailable;
use App\Modules\Identity\Domain\Exception\NotAQaAccount;

/**
 * Password-less sign-in for QA, by email alone.
 *
 * It exists because the only two real doors (Google, Apple) cannot be walked through on a
 * simulator — which had been blocking every live run of the app on anything but the owner's phone.
 * It is NOT a second production auth path: it can only reach accounts marked `is_qa`, and it can
 * only create accounts already marked that way.
 *
 * The port mirrors {@see GoogleSignIn} on purpose (same DTO, same device-name/timezone arguments),
 * so the client's sign-in flow is one branch and not a second implementation.
 */
interface DevSignIn
{
    /**
     * @param  string  $email  the QA address; created as a QA account when it does not exist yet
     * @param  string|null  $timezone  the device's IANA zone, seeded on the profile exactly as at
     *                                  Google sign-in
     *
     * @throws DevLoginUnavailable when the gate is shut (production, or the flag is off)
     * @throws NotAQaAccount when the address belongs to an account that is not `is_qa`
     */
    public function authenticate(string $email, string $deviceName, ?string $timezone = null): AuthResult;
}
