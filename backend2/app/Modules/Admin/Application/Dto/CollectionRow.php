<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One row of the admin collections list. */
final readonly class CollectionRow
{
    public function __construct(
        public string $id,
        public string $type,        // system | shared | custom
        public string $title,
        public ?string $ownerId,
        public string $source,      // curated | ai | user
        public int $itemsCount,
        public ?string $createdAt,
        public ?string $ownerEmail = null,
        /**
         * Directly-attributable spend (generation + realtime). The per-term purposes are left out
         * of the LIST on purpose — they need a join per row, and the collection card shows the
         * full split anyway.
         */
        public float $costUsd = 0.0,
    ) {}
}
