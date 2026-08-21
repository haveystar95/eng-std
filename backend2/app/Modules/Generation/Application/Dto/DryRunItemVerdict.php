<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One submitted row, the verdict the real validator gave it, and which check decided that. */
final readonly class DryRunItemVerdict
{
    public function __construct(
        public int $index,
        /** The row had no `error_type`, so a valid one was substituted before the run. */
        public bool $errorTypeDefaulted,
        public string $sentence,
        public string $errorSpan,
        public string $correction,
        public string $errorType,
        public bool $kept,
        /** Machine code: a {@see \App\Modules\Generation\Domain\ValueObject\DistractorGate}, or one
         *  of the dry run's own refinements of `duplicate` (see {@see DryRunReference}). */
        public string $gate,
        public string $reason,
    ) {}
}
