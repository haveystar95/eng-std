<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One paid model call made for a plan, as the ledger stores it. */
final readonly class PlanSpend
{
    public const CALL_OUTLINE = 'outline';
    public const CALL_DAY = 'day';

    /**
     * P2R — the short call that fixes a handful of a day's cards.
     *
     * Its own kind and not `day`, because the two answer different questions of the ledger. «What
     * did this plan cost» adds them up all the same; «why did day 1 cost $0.08» needs to be able
     * to see that one of the two rows bought fourteen cards and the other bought one.
     */
    public const CALL_DAY_REPAIR = 'day_repair';

    public function __construct(
        public string $planId,
        public string $userId,
        /** {@see CALL_OUTLINE} | {@see CALL_DAY} | {@see CALL_DAY_REPAIR} — which plan prompt this was. */
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
