<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * The grey line under the search field, and why it is (or is not) there.
 *
 * Every «nothing to show» state is a FIELD, not an exception: this is a hint on a debounced field,
 * and there is no failure here worth interrupting a person who is typing. The client shows the line
 * when there is one and shows nothing otherwise — it never has an error path to render.
 */
final readonly class InstantHintView
{
    public const SOURCE_VOCABULARY = 'vocabulary';
    public const SOURCE_CACHE = 'cache';

    private function __construct(
        public string $query,
        public ?string $translation,
        /** vocabulary | cache | <provider name> — internal, for the ledger and for tests. */
        public ?string $source,
        /** No provider is configured. Not an error: the rest of search is untouched. */
        public bool $featureDisabled = false,
        /** The month's character budget is spent. The full lookup keeps working. */
        public bool $limitReached = false,
        /**
         * The query was in the learner's OWN language, so `translation` is the word being learned.
         *
         * Internal, exactly like `source`: the screen uses it to decide which of the two strings is
         * the headline — the English one always is, because it is what was asked for — and it never
         * says a word about languages, directions or detection. A learner types and gets an answer.
         */
        public bool $reversed = false,
        /**
         * Longer than a phrase. Nothing was bought and nothing was asked; the screen says «поиск —
         * для слов и коротких фраз» and stops there.
         */
        public bool $queryTooLong = false,
    ) {}

    public static function hit(string $query, string $translation, string $source, bool $reversed = false): self
    {
        return new self($query, $translation, $source, reversed: $reversed);
    }

    /** A paragraph is not a query. See {@see \App\Modules\Generation\Domain\Service\SearchQueryLength}. */
    public static function tooLong(string $query): self
    {
        return new self($query, null, null, queryTooLong: true);
    }

    public static function nothing(string $query): self
    {
        return new self($query, null, null);
    }

    public static function disabled(string $query): self
    {
        return new self($query, null, null, featureDisabled: true);
    }

    /**
     * Out of budget — but a cached answer, if we happen to have one, is still served: it costs
     * nothing, and withholding a translation we already own to enforce a spending limit would
     * punish the learner for a bill that is not being incurred.
     */
    public static function outOfBudget(string $query, ?string $translation = null): self
    {
        return new self(
            $query,
            $translation,
            $translation !== null ? self::SOURCE_CACHE : null,
            limitReached: true,
        );
    }
}
