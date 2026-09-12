<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Forget a push address. With an owner (the device signing out) only that owner's row goes; with
 * none (APNs said the token is dead) the row goes whoever holds it.
 */
final readonly class RemovePushToken
{
    public function __construct(
        public string $platform,
        public string $token,
        public ?UserId $owner,
    ) {}
}
