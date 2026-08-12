<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What applying a review actually did. `unmatched` is the field that matters: a review entry that
 * matched no row is either a typo in the review or content that has already changed underneath it, and
 * either way it must be shown rather than counted as success.
 */
final readonly class ReviewOutcome
{
    /** @param  list<string>  $unmatched  human-readable description of each entry that hit nothing */
    public function __construct(
        public int $distractorsRemoved = 0,
        public int $distractorsFixed = 0,
        public int $variantsRemoved = 0,
        public int $variantNotesFixed = 0,
        public int $variantsAdded = 0,
        public int $variantsRejected = 0,
        public int $translationsFixed = 0,
        public int $exampleTranslationsFixed = 0,
        public int $findingsAcknowledged = 0,
        public array $unmatched = [],
    ) {}
}
