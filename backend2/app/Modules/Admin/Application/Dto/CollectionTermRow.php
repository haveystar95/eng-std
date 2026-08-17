<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A term inside a collection (detail view). */
final readonly class CollectionTermRow
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public int $position,
    ) {}
}
