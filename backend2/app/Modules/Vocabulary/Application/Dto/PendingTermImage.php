<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** A term still awaiting a photo: its id and the search query the model produced for it. */
final readonly class PendingTermImage
{
    public function __construct(
        public string $termId,
        public string $query,
    ) {}
}
