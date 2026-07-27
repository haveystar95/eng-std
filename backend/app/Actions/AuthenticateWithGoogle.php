<?php

namespace App\Actions;

use App\Models\Profile;
use App\Models\User;
use App\Services\GoogleAuthenticator;
use App\Services\StarterContent;
use Illuminate\Validation\ValidationException;

/**
 * Verifies a Google ID token, upserts the user + profile, seeds starter
 * content for new users, and issues a Sanctum token.
 */
class AuthenticateWithGoogle
{
    public function __construct(
        private readonly GoogleAuthenticator $google,
        private readonly StarterContent $starter,
    ) {}

    /** @return array{user: User, token: string} */
    public function handle(string $idToken): array
    {
        $payload = $this->google->verify($idToken);
        if ($payload === null) {
            throw ValidationException::withMessages(['id_token' => 'Invalid Google token.']);
        }

        $user = User::firstOrNew(['google_id' => $payload['sub']]);
        $user->fill([
            'name' => $payload['name'] ?? ($user->name ?? 'Learner'),
            'email' => $payload['email'] ?: $user->email,
            'avatar' => $payload['picture'],
        ])->save();

        Profile::firstOrCreate(['user_id' => $user->id]);

        if ($user->wasRecentlyCreated) {
            $this->starter->seed($user);
        }

        return [
            'user' => $user->fresh('profile'),
            'token' => $user->createToken('mobile')->plainTextToken,
        ];
    }
}
