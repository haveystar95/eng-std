<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One row of the generations list (AI collection-generation requests). */
final readonly class GenerationRow
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $prompt,
        public string $status,      // pending | running | succeeded | failed
        public ?string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?float $costUsd,
        public ?string $collectionId,
        public ?string $error,
        public ?string $createdAt,
        public ?string $finishedAt,
    ) {}
}
