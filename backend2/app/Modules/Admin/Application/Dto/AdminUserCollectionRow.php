<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A collection the user has in their library. */
final readonly class AdminUserCollectionRow
{
    public function __construct(
        public string $id,
        public string $title,
        public string $type,
        public int $itemsCount,
        public ?string $addedAt,
    ) {}
}
