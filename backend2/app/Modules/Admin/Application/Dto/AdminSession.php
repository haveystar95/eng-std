<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A freshly-authenticated admin: the bearer token plus who it belongs to. */
final readonly class AdminSession
{
    public function __construct(
        public string $token,
        public string $id,
        public string $email,
        public string $name,
    ) {}
}
