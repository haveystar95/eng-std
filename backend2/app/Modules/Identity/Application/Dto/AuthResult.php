<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** Outcome of a successful sign-in: a Sanctum bearer token plus the authenticated user. */
final readonly class AuthResult
{
    public function __construct(
        public string $token,
        public UserView $user,
    ) {}
}
