<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One of a user's collections with its per-collection progress counters. */
final readonly class UserCollectionWithProgress
{
    public function __construct(
        public string $id,
        public string $title,
        public string $type,
        public int $itemsCount,
        public ?string $addedAt,
        public AdminCollectionProgress $progress,
    ) {}
}
