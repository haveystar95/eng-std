<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * A stock photo found for a search query: the image URL plus the photographer credit Pexels'
 * licence requires (name + link). `author`/`authorUrl` are nullable defensively — the provider
 * normally supplies both, but a missing credit must not drop an otherwise-usable image.
 */
final readonly class ImageResult
{
    public function __construct(
        public string $url,
        public ?string $author,
        public ?string $authorUrl,
    ) {}
}
