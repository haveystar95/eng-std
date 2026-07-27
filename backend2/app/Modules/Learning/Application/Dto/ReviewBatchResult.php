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
    ) {}
}
