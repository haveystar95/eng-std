<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

use DateTimeImmutable;

/**
 * THE LEARNER'S ACCESS NOW (наряд ACC-1 §2) — what `GET /auth/me` says in `access` and what the plan's paywall asks:
 * the paid plan or the free one, until when (null — no end, or the free plan) and by which right.
 */
final readonly class Access
{
    public function __construct(
        public AccessPlan $plan,
        public ?DateTimeImmutable $expiresAt,
        public ?EntitlementSource $source,
    ) {}

    public static function free(): self
    {
        return new self(AccessPlan::Free, null, null);
    }

    public function isPremium(): bool
    {
        return $this->plan === AccessPlan::Premium;
    }
}
