<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use DateTimeImmutable;

/** List-row view of a collection — summary only, no items (bandwidth). */
final readonly class CollectionSummaryView
{
    public function __construct(
        public string $id,
        public string $type,
        public string $source,
        public string $title,
        public ?string $description,
        public string $sourceLang,
        public string $targetLang,
        public string $visibility,
        public int $itemsCount,
        public DateTimeImmutable $createdAt,
        /** «Сохранённые»: the folder an unaddressed save lands in. Renameable, never deletable. */
        public bool $isDefault = false,
    ) {}
}
