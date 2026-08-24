<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Outcome of a triage batch: newly applied, ignored as duplicates, skipped as unknown terms, or
 * REFUSED because the word is in a language this product does not teach.
 *
 * The last bucket is separate from `unknown` because the two are different facts and the client
 * would act differently on them: `unknown` is a term id this account cannot see (a stale device,
 * a deleted word) and it goes away by itself, while `rejected` is a word that exists and will
 * never be trainable — a reference-language term (DECISIONS пп. 84, 136). Folding it into
 * `unknown` would tell a device to keep retrying a swipe that can never land.
 *
 * A refused ITEM and not a refused BATCH: an offline queue uploads whatever the deck collected, and
 * one impossible word must not cost the other nineteen their verdicts (п. 101).
 */
final readonly class TriageBatchResult
{
    public function __construct(
        public int $accepted,
        public int $duplicates,
        public int $unknown,
        public int $rejected = 0,
    ) {}
}
