<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** The plan row as stored, for the admin's plan page (наряд ADM-1). */
final readonly class InspectedPlan
{
    /** @param list<array<string, mixed>> $checks `checks_json` as stored: `{check, mode, action, detail}` */
    public function __construct(
        public string $id,
        public string $userId,
        public string $status,
        public ?string $promptVersion,
        public ?string $buildVersion,
        public ?string $model,
        public ?string $costUsd,
        public ?int $latencyMs,
        public ?int $attempts,
        public array $checks,
        public ?string $unclearReason,
        public ?string $failReason,
        public ?DateTimeImmutable $buildStartedAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public ?string $coverImageUrl,
    ) {}
}
