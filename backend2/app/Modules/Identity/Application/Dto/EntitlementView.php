<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** One right of the learner as the admin's page reads it (наряд ACC-1 §2) — the row, and whether it is in force now. */
final readonly class EntitlementView
{
    public function __construct(
        public string $source,
        public string $product,
        public string $status,
        public string $startsAt,
        public ?string $expiresAt,
        public string $updatedAt,
        public bool $active,
    ) {}

    /** @return array{source: string, product: string, status: string, starts_at: string, expires_at: string|null, updated_at: string, active: bool} */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'product' => $this->product,
            'status' => $this->status,
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'updated_at' => $this->updatedAt,
            'active' => $this->active,
        ];
    }
}
