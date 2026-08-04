<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/** Read model for GET /generations/{id} and the console output. */
final readonly class GenerationRequestView
{
    public function __construct(
        public string $id,
        public string $status,
        public string $prompt,
        public int $requested,        // how many items the user asked for
        public ?int $delivered,       // how many actually landed; null until succeeded
        public ?string $collectionId,
        public ?string $error,
        public ?string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $finishedAt,
    ) {}
}
