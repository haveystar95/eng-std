<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

use App\Modules\Identity\Domain\ValueObject\AccessPlan;

/**
 * THE LEARNER'S ACCESS AS THE CLIENT READS IT (наряд ACC-1 §2, `GET /auth/me` → `access`): `plan` — `free` | `premium`;
 * `expires_at` — until when the paid plan is open (ISO-8601; null — no end, or the free plan); `source` — by which right
 * (`admin` | `promo` | `apple` | `google`; null for the free plan).
 */
final readonly class AccessView
{
    public function __construct(
        public string $plan,
        public ?string $expiresAt,
        public ?string $source,
    ) {}

    public function isPremium(): bool
    {
        return $this->plan === AccessPlan::Premium->value;
    }

    /** @return array{plan: string, expires_at: string|null, source: string|null} */
    public function toArray(): array
    {
        return ['plan' => $this->plan, 'expires_at' => $this->expiresAt, 'source' => $this->source];
    }
}
