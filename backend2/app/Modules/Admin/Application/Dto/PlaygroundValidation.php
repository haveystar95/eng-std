<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * A dry run of the real enrichment validator: what it would have kept, and why it dropped the rest.
 * Nothing here was written anywhere — see {@see \App\Modules\Generation\Application\Service\DistractorDryRun}.
 */
final readonly class PlaygroundValidation
{
    /** @param  list<PlaygroundValidationRow>  $items */
    public function __construct(
        public array $items,
        public int $kept,
        public int $total,
        /** The reference the rows were judged against: a real term, or a hand-typed pair. */
        public string $source,             // term | manual
        public ?string $termId,
        public string $termText,
        public ?string $exampleSentence,
        public int $existingCount,
        public int $suppressedCount,
        /** Manual mode only: the term the text matched, whose dedup set and suppressions were used. */
        public ?string $matchedTermId = null,
    ) {}
}
