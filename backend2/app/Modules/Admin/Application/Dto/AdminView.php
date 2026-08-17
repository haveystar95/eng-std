<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A back-office admin as the panel sees itself (no secrets). */
final readonly class AdminView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
    ) {}
}
