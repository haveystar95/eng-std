<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Generation\Domain\ValueObject\PlanViolation;
use RuntimeException;

/**
 * One answer for one day, judged and refused — with the verdict kept as a LIST rather than as a
 * sentence.
 *
 * Until v0.3 this was a plain `RuntimeException` whose message was the violations glued together,
 * and the message went into `fail_reason`, truncated to 500 characters. That was enough for a
 * person reading the plan screen and not enough for the only other reader that matters: the NEXT
 * attempt. A retry is worth paying for exactly to the extent that it knows what was wrong with the
 * previous answer, and half a sentence about the eighteenth violation is not that.
 *
 * So the violations travel as data all the way from the gate to the day row
 * ({@see \App\Modules\Learning\Domain\Entity\PlanDay::markFailed()}), where they accumulate across
 * attempts and are quoted into the next call. The message is still built, because a human still
 * reads `fail_reason`.
 */
final class PlanDayRefused extends RuntimeException
{
    /** @param list<string> $violations */
    private function __construct(string $message, public readonly array $violations)
    {
        parent::__construct($message);
    }

    /** @param list<PlanViolation> $violations */
    public static function invalid(array $violations): self
    {
        $lines = array_map(static fn (PlanViolation $v): string => (string) $v, $violations);

        return new self('День не прошёл валидатор: ' . implode('; ', $lines), $lines);
    }
}
