<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Dto\AuthResult;
use App\Modules\Identity\Application\Port\GoogleSignIn;
use App\Modules\Identity\Application\Port\GoogleTokenVerifier;
use App\Modules\Identity\Domain\Exception\InvalidGoogleToken;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Identity\Infrastructure\Eloquent\UserViewMapper;

/**
 * Verify → upsert user (keyed by Google `sub`) → ensure a profile exists → issue a
 * per-device Sanctum token. Idempotent across logins: the same Google account maps to the
 * same user row, each login just mints a fresh token.
 */
final readonly class SanctumGoogleSignIn implements GoogleSignIn
{
    public function __construct(
        private GoogleTokenVerifier $verifier,
        private UserViewMapper $mapper,
    ) {}

    public function authenticate(string $idToken, string $deviceName, ?string $timezone = null, ?string $nativeLanguage = null): AuthResult
    {
        $identity = $this->verifier->verify($idToken);
        if ($identity === null) {
            throw InvalidGoogleToken::make();
        }

        $user = User::query()->firstOrNew(['google_id' => $identity->sub]);
        $user->fill([
            'name' => $identity->name ?? $user->name ?? 'Learner',
            'email' => $identity->email !== '' ? $identity->email : $user->email,
            'avatar' => $identity->picture,
        ])->save();

        // Every user has exactly one profile; create it with defaults on first sign-in. Seed the
        // timezone so calendar-day due rounding (F19) works from the very first review; refresh it
        // on later logins too (the device may have moved), but never blank a stored zone.
        //
        // The native language is the other way round (наряд LANG-1 §7): the device's language seeds it ONLY
        // in the row this sign-in creates — `firstOrCreate`'s values are written on the insert and never on a
        // found row — because a stored native is the learner's own choice, and a phone switched to Polish
        // for a holiday must not turn their Russian plan screens Polish on the next login.
        $profile = $user->profile()->firstOrCreate([], $nativeLanguage !== null ? ['native_language' => $nativeLanguage] : []);
        if ($timezone !== null && $timezone !== '' && $profile->timezone !== $timezone) {
            $profile->timezone = $timezone;
            $profile->save();
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return new AuthResult(
            token: $token,
            user: $this->mapper->toView($user),
        );
    }
}
