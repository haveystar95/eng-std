<?php

namespace App\Services;

use Google\Auth\AccessToken;

/**
 * Verifies a Google ID token (obtained by the mobile app via native Google
 * Sign-In) and returns its trusted payload, or null if invalid.
 */
class GoogleAuthenticator
{
    /** @param string[] $clientIds Accepted OAuth client IDs (iOS + web/server). */
    public function __construct(private readonly array $clientIds) {}

    /**
     * @return array{sub:string,email:string,name:?string,picture:?string}|null
     */
    public function verify(string $idToken): ?array
    {
        $verifier = new AccessToken();

        foreach ($this->clientIds as $clientId) {
            if (! $clientId) {
                continue;
            }

            $payload = $verifier->verify($idToken, ['audience' => $clientId]);
            if ($payload !== false) {
                return [
                    'sub' => $payload['sub'],
                    'email' => $payload['email'] ?? '',
                    'name' => $payload['name'] ?? null,
                    'picture' => $payload['picture'] ?? null,
                ];
            }
        }

        return null;
    }
}
