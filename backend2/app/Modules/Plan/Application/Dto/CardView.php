<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One card as the client receives it: the payload with images and audio resolved, and its state. */
final readonly class CardView
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $stage,
        public int $position,
        public string $kind,
        public string $source,
        public ?string $sourceDayId,
        public string $unitKind,
        public string $unitRef,
        public array $payload,
        public ?string $retryOf,
        public ?string $result,
        public int $attempts,
        public ?string $answeredAt,
        public bool $returns,
    ) {}
}
