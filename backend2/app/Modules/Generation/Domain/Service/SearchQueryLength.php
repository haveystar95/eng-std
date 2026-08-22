<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * How long a thing may be and still be a search QUERY.
 *
 * 120 characters is a long phrase and a short sentence, and the line is drawn there on purpose:
 * this app catches words and phrases, and everything it does with one — a card, a description, an
 * example, a place in the pool — is meaningless for a paragraph. A field that quietly accepted a
 * paragraph would be a translator, and a translator is a different product.
 *
 * The limit is enforced BEFORE the vendor, not by it. The characters are what the free plan bills,
 * so a pasted page is a bill and not merely a bad answer; and «too long» is a thing the app can say
 * plainly («Поиск — для слов и коротких фраз») rather than a silence the learner has to interpret.
 *
 * Counted in CHARACTERS and not bytes: a Russian query would otherwise be cut at half the length of
 * an English one, which is the same rule applied twice as harshly to the people it matters most to.
 */
final readonly class SearchQueryLength
{
    public const DEFAULT_MAX = 120;

    public function __construct(private int $maxCharacters = self::DEFAULT_MAX) {}

    public function max(): int
    {
        return $this->maxCharacters > 0 ? $this->maxCharacters : self::DEFAULT_MAX;
    }

    public function exceeded(string $query): bool
    {
        return mb_strlen(trim($query)) > $this->max();
    }
}
