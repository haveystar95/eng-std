<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One search of a batch: the words and which page of the answers to take the first photo from. */
final readonly class ImageSearchQuery
{
    public function __construct(
        public string $query,
        public int $page = 1,
    ) {}
}
