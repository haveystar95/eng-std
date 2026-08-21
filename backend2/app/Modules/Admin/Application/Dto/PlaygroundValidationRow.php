<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One submitted distractor, the real validator's verdict, and the check that decided it. */
final readonly class PlaygroundValidationRow
{
    public function __construct(
        public int $index,
        public string $sentence,
        public string $errorSpan,
        public string $correction,
        public string $errorType,
        public bool $kept,
        public string $gate,
        public string $reason,
        /**
         * The row arrived without an `error_type`, so a valid one was substituted before the run.
         * Said out loud rather than defaulted quietly: without it every such row would be refused by
         * the type check and the person would never see what the other twelve checks thought.
         */
        public bool $errorTypeDefaulted,
    ) {}
}
