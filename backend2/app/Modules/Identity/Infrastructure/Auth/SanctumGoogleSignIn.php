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

    public function authenticate(string $idToken, string $deviceName): AuthResult
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

        // Every user has exactly one profile; create it with defaults on first sign-in.
        $user->profile()->firstOrCreate([]);

        $token = $user->createToken($deviceName)->plainTextToken;

        return new AuthResult(
            token: $token,
            user: $this->mapper->toView($user),
        );
    }
}
