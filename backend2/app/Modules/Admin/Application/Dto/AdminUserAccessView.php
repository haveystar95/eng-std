<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * A learner's access as the admin's page shows it (наряд ACC-1 §2): the access as the client is told it, every right
 * behind it (in force or not), and whether the paywall is switched on at all. Plain shapes: the page reads Identity's
 * answer as it is, and the Presentation layer of this module does not import Identity.
 */
final readonly class AdminUserAccessView
{
    /**
     * @param  array{plan: string, expires_at: string|null, source: string|null}  $access
     * @param  list<array{source: string, product: string, status: string, starts_at: string, expires_at: string|null, updated_at: string, active: bool}>  $entitlements
     */
    public function __construct(
        public array $access,
        public array $entitlements,
        public bool $paywallEnabled,
    ) {}
}
