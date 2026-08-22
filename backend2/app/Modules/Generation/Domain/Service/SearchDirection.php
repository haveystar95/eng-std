<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Which way one search query is being translated: from what, into what.
 *
 * Carried as a pair rather than as a boolean because it is also the CACHE KEY — a translation of
 * «случай» into English and a translation of «occasion» into Russian are two different facts about
 * two different words, and a key that only said «reversed» would let one overwrite the other.
 */
final readonly class SearchDirection
{
    public function __construct(
        public string $source,
        public string $target,
    ) {}

    /** `ru:en` — the stored form, and the only form the cache ever sees. */
    public function pair(): string
    {
        return $this->source . ':' . $this->target;
    }

    public function equals(self $other): bool
    {
        return $this->source === $other->source && $this->target === $other->target;
    }
}
