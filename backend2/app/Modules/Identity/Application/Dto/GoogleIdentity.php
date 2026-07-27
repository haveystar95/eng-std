<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** The trusted claims extracted from a verified Google ID token. */
final readonly class GoogleIdentity
{
    public function __construct(
        public string $sub,          // stable Google account id
        public string $email,
        public ?string $name,
        public ?string $picture,
    ) {}
}
