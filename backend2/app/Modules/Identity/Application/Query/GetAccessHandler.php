<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Identity\Application\Dto\AccessView;
use App\Modules\Identity\Application\Port\EntitlementStore;
use App\Modules\Identity\Domain\Service\AccessRule;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * The learner's access, read over every right they have by {@see AccessRule} — what `GET /auth/me` says in `access`, and
 * the one question the plan's paywall asks of this module (Plan's `IdentityLearnerAccess`).
 */
final readonly class GetAccessHandler
{
    public function __construct(
        private EntitlementStore $entitlements,
        private Clock $clock,
    ) {}

    public function __invoke(GetAccess $query): AccessView
    {
        $access = AccessRule::of($this->entitlements->forUser($query->userId), $this->clock->now());

        return new AccessView($access->plan->value, $access->expiresAt?->format(DATE_ATOM), $access->source?->value);
    }
}
