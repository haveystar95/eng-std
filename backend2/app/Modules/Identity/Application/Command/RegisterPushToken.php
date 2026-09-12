<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class RegisterPushToken
{
    public function __construct(
        public UserId $userId,
        public string $platform,
        public string $token,
        public ?string $locale,
        public ?string $timezone,
    ) {}
}
