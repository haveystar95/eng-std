<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/** A term's membership in a collection. Term content is hydrated separately from Vocabulary. */
final readonly class CollectionItemView
{
    public function __construct(
        public string $termId,
        public int $position,
        public ?string $note,
    ) {}
}
