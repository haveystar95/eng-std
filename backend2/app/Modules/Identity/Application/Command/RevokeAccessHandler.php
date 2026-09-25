<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Application\Port\EntitlementStore;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class RevokeAccessHandler
{
    public function __construct(
        private EntitlementStore $entitlements,
        private Clock $clock,
    ) {}

    /** @return int how many rights were in force and are not any more */
    public function __invoke(RevokeAccess $command): int
    {
        return $this->entitlements->revokeAll($command->userId, $this->clock->now());
    }
}
