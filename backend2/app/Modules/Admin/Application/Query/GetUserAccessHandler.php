<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminUserAccessView;
use App\Modules\Admin\Application\Dto\PaywallSwitch;
use App\Modules\Identity\Application\Dto\EntitlementView;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Identity\Application\Query\GetAccess;
use App\Modules\Identity\Application\Query\GetAccessHandler;
use App\Modules\Identity\Application\Query\GetEntitlements;
use App\Modules\Identity\Application\Query\GetEntitlementsHandler;

/**
 * A learner's access, asked of Identity — the owner of the rights and of the rule that reads them — and nothing
 * re-counted here (the Admin rule: meaning is asked of its module, never reinvented in SQL). Null for no such learner.
 */
final readonly class GetUserAccessHandler
{
    public function __construct(
        private UserReader $users,
        private GetAccessHandler $access,
        private GetEntitlementsHandler $entitlements,
        private PaywallSwitch $paywall,
    ) {}

    public function __invoke(GetUserAccess $query): ?AdminUserAccessView
    {
        if ($this->users->byId($query->userId) === null) {
            return null;
        }

        return new AdminUserAccessView(
            access: ($this->access)(new GetAccess($query->userId))->toArray(),
            entitlements: array_map(static fn (EntitlementView $e): array => $e->toArray(), ($this->entitlements)(new GetEntitlements($query->userId))),
            paywallEnabled: $this->paywall->enabled,
        );
    }
}
