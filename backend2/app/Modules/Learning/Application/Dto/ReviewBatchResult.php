<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** Outcome of a review batch: how many were newly applied, ignored as duplicates, or skipped as unknown. */
final readonly class ReviewBatchResult
{
    public function __construct(
        public int $accepted,
        public int $duplicates,
        public int $unknown,
        /**
         * Intro cards newly recorded. Counted apart from `accepted` because they are not answers:
         * folding them into one number would tell the client it had uploaded retrievals it never
         * made, and a re-upload legitimately reports zero here while changing nothing.
         */
        public int $exposures = 0,
    ) {}
}
