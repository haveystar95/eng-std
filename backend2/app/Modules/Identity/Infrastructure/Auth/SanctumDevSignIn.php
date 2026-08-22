<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Dto\AuthResult;
use App\Modules\Identity\Application\Port\DevSignIn;
use App\Modules\Identity\Domain\Exception\DevLoginUnavailable;
use App\Modules\Identity\Domain\Exception\NotAQaAccount;
use App\Modules\Identity\Domain\Service\DevLoginGate;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Identity\Infrastructure\Eloquent\UserViewMapper;

/**
 * The dev door: email in, Sanctum token out — no credential of any kind.
 *
 * Three locks, checked in this order and every time:
 *
 *   1. {@see DevLoginGate} — environment is not production AND the opt-in flag is on. Asked HERE
 *      and not only at route registration, so a reachable path is still a shut door.
 *   2. the address must be free, or belong to an account already marked `is_qa`. A real account
 *      (the owner's Google one) is refused with 403.
 *   3. an account this door creates is born `is_qa = true` and with no `google_id` — so it can
 *      never be confused with, or upgraded into, a real sign-in.
 *
 * Apart from that it is deliberately identical to {@see SanctumGoogleSignIn}: same profile
 * creation, same timezone seeding, same per-device token. QA has to exercise the app the learner
 * uses, not a parallel one.
 */
final readonly class SanctumDevSignIn implements DevSignIn
{
    public function __construct(private UserViewMapper $mapper) {}

    public function authenticate(string $email, string $deviceName, ?string $timezone = null): AuthResult
    {
        if (! DevLoginGate::isOpen((string) app()->environment(), (bool) config('qa.dev_login'))) {
            throw DevLoginUnavailable::make();
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! (bool) $user->is_qa) {
            throw NotAQaAccount::forEmail($email);
        }

        if ($user === null) {
            $user = new User();
            $user->fill([
                'name' => self::nameFromEmail($email),
                'email' => $email,
                'is_qa' => true,
            ]);
            $user->save();
        }

        // Same as the Google path: exactly one profile per user, created with defaults on first
        // sign-in, and the device's zone seeded so calendar-day due rounding (F19) is right from
        // the first review — a QA run is worthless if its dates are rounded in a zone the run
        // never used.
        $profile = $user->profile()->firstOrCreate([]);
        if ($timezone !== null && $timezone !== '' && $profile->timezone !== $timezone) {
            $profile->timezone = $timezone;
            $profile->save();
        }

        return new AuthResult(
            token: $user->createToken($deviceName)->plainTextToken,
            user: $this->mapper->toView($user),
        );
    }

    /** `qa@wt.test` → `Qa`. A name has to be something; the address is the only thing we know. */
    private static function nameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true);
        $local = $local === false ? $email : $local;

        return $local === '' ? 'QA' : ucfirst($local);
    }
}
