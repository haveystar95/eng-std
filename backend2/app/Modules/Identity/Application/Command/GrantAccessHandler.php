<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Application\Port\EntitlementStore;
use App\Modules\Identity\Domain\ValueObject\Entitlement;
use App\Modules\Identity\Domain\ValueObject\EntitlementProduct;
use App\Modules\Identity\Domain\ValueObject\EntitlementStatus;
use App\Modules\Shared\Domain\Service\Clock;
use InvalidArgumentException;

final readonly class GrantAccessHandler
{
    public function __construct(
        private EntitlementStore $entitlements,
        private Clock $clock,
    ) {}

    /** @return Entitlement the right as written */
    public function __invoke(GrantAccess $command): Entitlement
    {
        if ($command->product === EntitlementProduct::Lifetime && $command->until !== null) {
            throw new InvalidArgumentException('A lifetime right has no end: --until is for a month or a year.');
        }
        $now = $this->clock->now();
        $entitlement = new Entitlement(
            source: $command->source,
            product: $command->product,
            status: EntitlementStatus::Active,
            startsAt: $now,
            expiresAt: $command->until ?? $command->product->expiresAfter($now),
            updatedAt: $now,
        );
        $this->entitlements->put($command->userId, $entitlement);

        return $entitlement;
    }
}
