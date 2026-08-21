<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use DateTimeImmutable;

/**
 * The instant-translation cache and, in the same place, the meter that keeps it affordable.
 *
 * The two live together because they are the same rows read two ways: every cached translation IS
 * a record of characters bought. Splitting them would create a counter that can disagree with the
 * cache — and the disagreement would only ever surface as a surprise bill.
 */
interface InstantTranslationCache
{
    /** The stored translation for this word and direction, or null. Free, for everybody, forever. */
    public function find(string $normalizedText, string $langPair): ?string;

    /**
     * Characters this provider has been billed for since `$since` — the month's meter.
     *
     * A SUM over rows rather than a stored total, so it cannot drift: a row that exists was paid
     * for, and a row that does not was not.
     */
    public function charactersUsedSince(string $provider, DateTimeImmutable $since): int;

    /**
     * Store a freshly bought translation.
     *
     * Must tolerate a concurrent writer having stored the same word first — two people can type it
     * in the same second — and must not fail the request when it happens: the caller already has
     * the answer it is going to show.
     */
    public function store(
        string $normalizedText,
        string $langPair,
        string $translation,
        string $provider,
        int $characters,
    ): void;
}
