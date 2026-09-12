<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One device address a letter goes to. */
final readonly class PushTarget
{
    public function __construct(
        public string $platform,
        public string $token,
    ) {}
}
