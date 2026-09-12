<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * A stock photo found for a search query: the image URL plus the photographer credit Pexels'
 * licence requires (name + link). `author`/`authorUrl` are nullable defensively — the provider
 * normally supplies both, but a missing credit must not drop an otherwise-usable image.
 * `avgColor` is the photo's average colour as the vendor reports it (`#978E82`) — the plan paints a
 * placeholder with it; collections ignore it.
 */
final readonly class ImageResult
{
    public function __construct(
        public string $url,
        public ?string $author,
        public ?string $authorUrl,
        public ?string $avgColor = null,
    ) {}
}
