<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Identity\Application\Dto\EntitlementView;
use App\Modules\Identity\Application\Port\EntitlementStore;
use App\Modules\Identity\Domain\ValueObject\Entitlement;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class GetEntitlementsHandler
{
    public function __construct(
        private EntitlementStore $entitlements,
        private Clock $clock,
    ) {}

    /** @return list<EntitlementView> */
    public function __invoke(GetEntitlements $query): array
    {
        $now = $this->clock->now();

        return array_map(static fn (Entitlement $e): EntitlementView => new EntitlementView(
            source: $e->source->value,
            product: $e->product->value,
            status: $e->status->value,
            startsAt: $e->startsAt->format(DATE_ATOM),
            expiresAt: $e->expiresAt?->format(DATE_ATOM),
            updatedAt: $e->updatedAt->format(DATE_ATOM),
            active: $e->isActiveAt($now),
        ), $this->entitlements->forUser($query->userId));
    }
}
