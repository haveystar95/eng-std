<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Application\Port\PushTokenStore;
use App\Modules\Shared\Domain\Service\Clock;

/** `PUT /devices/push-token`: the address is upserted; a token seen under another account moves to this one. */
final readonly class RegisterPushTokenHandler
{
    public function __construct(
        private PushTokenStore $tokens,
        private Clock $clock,
    ) {}

    public function __invoke(RegisterPushToken $command): void
    {
        $this->tokens->upsert(
            $command->userId,
            $command->platform,
            trim($command->token),
            $command->locale,
            $command->timezone,
            $this->clock->now(),
        );
    }
}
