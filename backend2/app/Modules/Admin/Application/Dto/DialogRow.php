<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One row of the practice-dialogs list. */
final readonly class DialogRow
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $collectionId,
        public string $status,      // active | finished | expired
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?float $costUsd,
        public ?string $createdAt,
        public ?string $finishedAt,
    ) {}
}
