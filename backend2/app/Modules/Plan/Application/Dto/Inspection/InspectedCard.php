<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** A dealt card with what came back for it, as stored (`day_cards`), for the admin's plan page (наряд ADM-1). */
final readonly class InspectedCard
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        public string $id,
        public string $dayId,
        public string $stage,
        public int $position,
        public string $kind,
        public array $payload,
        public string $source,
        public ?string $sourceDayId,
        public string $unitKind,
        public string $unitRef,
        public ?string $retryOf,
        public ?string $result,
        public int $attempts,
        public ?DateTimeImmutable $answeredAt,
        public bool $returns,
        public ?array $response,
    ) {}
}
