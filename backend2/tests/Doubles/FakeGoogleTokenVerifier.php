<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Identity\Application\Dto\GoogleIdentity;
use App\Modules\Identity\Application\Port\GoogleTokenVerifier;

/** Returns a fixed identity (or rejects) so feature tests never call Google. */
final class FakeGoogleTokenVerifier implements GoogleTokenVerifier
{
    private function __construct(private readonly ?GoogleIdentity $identity) {}

    public static function returning(GoogleIdentity $identity): self
    {
        return new self($identity);
    }

    public static function rejecting(): self
    {
        return new self(null);
    }

    public function verify(string $idToken): ?GoogleIdentity
    {
        return $this->identity;
    }
}
