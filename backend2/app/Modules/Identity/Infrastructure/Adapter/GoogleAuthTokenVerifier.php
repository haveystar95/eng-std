<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Adapter;

use App\Modules\Identity\Application\Dto\GoogleIdentity;
use App\Modules\Identity\Application\Port\GoogleTokenVerifier;
use Google\Auth\AccessToken;

/**
 * Verifies Google ID tokens against the app's accepted OAuth client ids (iOS + optional
 * web/server). A token is trusted only if its audience matches one of them.
 */
final class GoogleAuthTokenVerifier implements GoogleTokenVerifier
{
    /** @param list<string> $clientIds */
    public function __construct(private readonly array $clientIds) {}

    public function verify(string $idToken): ?GoogleIdentity
    {
        $verifier = new AccessToken();

        foreach ($this->clientIds as $clientId) {
            if ($clientId === '') {
                continue;
            }

            $payload = $verifier->verify($idToken, ['audience' => $clientId]);
            if (is_array($payload) && isset($payload['sub'])) {
                return new GoogleIdentity(
                    sub: (string) $payload['sub'],
                    email: isset($payload['email']) ? (string) $payload['email'] : '',
                    name: isset($payload['name']) ? (string) $payload['name'] : null,
                    picture: isset($payload['picture']) ? (string) $payload['picture'] : null,
                );
            }
        }

        return null;
    }
}
