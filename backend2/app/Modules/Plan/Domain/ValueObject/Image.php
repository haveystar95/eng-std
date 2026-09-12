<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * A found stock photo with the credit its licence asks for, and its tone — the photo's average
 * colour (`#978E82`), what the client paints the circle with before the bytes arrive. A tone that
 * is not a six-digit hex colour is no tone at all: the photo is still good without one.
 */
final readonly class Image
{
    public ?string $tone;

    public function __construct(
        public string $url,
        public ?string $author,
        public ?string $authorUrl,
        ?string $tone = null,
    ) {
        $this->tone = self::normalTone($tone);
    }

    public static function normalTone(?string $tone): ?string
    {
        $tone = $tone === null ? '' : trim($tone);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $tone) === 1 ? strtoupper($tone) : null;
    }

    /**
     * The version of the photo's bytes: a scene's source URL is written once and never changes,
     * so a digest of it names the bytes for as long as they exist — which is what lets the sized
     * copies be served `immutable`.
     */
    public function version(): string
    {
        return substr(sha1($this->url), 0, 12);
    }

    /** @return array{url: string, author: string|null, author_url: string|null} */
    public function toArray(): array
    {
        return ['url' => $this->url, 'author' => $this->author, 'author_url' => $this->authorUrl];
    }
}
