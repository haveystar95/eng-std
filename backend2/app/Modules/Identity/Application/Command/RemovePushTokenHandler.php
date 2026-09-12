<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Application\Port\PushTokenStore;

final readonly class RemovePushTokenHandler
{
    public function __construct(private PushTokenStore $tokens) {}

    public function __invoke(RemovePushToken $command): void
    {
        $this->tokens->remove($command->platform, trim($command->token), $command->owner);
    }
}
