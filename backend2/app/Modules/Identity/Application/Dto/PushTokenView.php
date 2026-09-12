<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** One push address of one of the learner's devices. */
final readonly class PushTokenView
{
    public function __construct(
        public string $platform,
        public string $token,
        public ?string $locale,
        public ?string $timezone,
        public string $lastSeenAt,
    ) {}
}
