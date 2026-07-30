<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** Outcome of a triage batch: newly applied, ignored as duplicates, or skipped as unknown terms. */
final readonly class TriageBatchResult
{
    public function __construct(
        public int $accepted,
        public int $duplicates,
        public int $unknown,
    ) {}
}
