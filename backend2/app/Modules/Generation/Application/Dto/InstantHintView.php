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
    ) {}

    public static function hit(string $query, string $translation, string $source): self
    {
        return new self($query, $translation, $source);
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
