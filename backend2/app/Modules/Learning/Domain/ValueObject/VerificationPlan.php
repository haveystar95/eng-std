<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * When to check a "known" self-assessment. The verification always runs in typing (recognition
 * would just let the user recognise the word again and prove nothing) — that mode is enforced
 * by the exercise selector, so the plan only carries the timing.
 */
final readonly class VerificationPlan
{
    public function __construct(
        public int $dueInDays,
        public bool $risky,
    ) {}
}
