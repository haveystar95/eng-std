<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One paid model call made for a plan, as the ledger stores it. */
final readonly class PlanSpend
{
    public const CALL_OUTLINE = 'outline';
    public const CALL_DAY = 'day';

    public function __construct(
        public string $planId,
        public string $userId,
        /** {@see CALL_OUTLINE} | {@see CALL_DAY} — which of the two plan prompts this was. */
        public string $call,
        /** What was asked for, for a human reading the ledger: the goal, or «день N — заголовок». */
        public string $subject,
        public string $supportLang,
        public string $targetLang,
        public string $promptVersion,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        /** Terms asked for on a day call; 0 for an outline, which produces none. */
        public int $size = 0,
        /** False when the answer was refused by a validator — the call still cost money. */
        public bool $succeeded = true,
        public ?string $error = null,
    ) {}
}
