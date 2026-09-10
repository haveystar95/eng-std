<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** A found stock photo with the credit its licence asks for. */
final readonly class Image
{
    public function __construct(
        public string $url,
        public ?string $author,
        public ?string $authorUrl,
    ) {}

    /** @return array{url: string, author: string|null, author_url: string|null} */
    public function toArray(): array
    {
        return ['url' => $this->url, 'author' => $this->author, 'author_url' => $this->authorUrl];
    }
}
