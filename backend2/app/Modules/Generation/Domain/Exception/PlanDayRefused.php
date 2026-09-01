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
 * ({@see \App\Modules\Learning\Domain\Entity\PlanDay::markFailed()}), where the next attempt reads
 * them. The message is still built, because a human still reads `fail_reason`.
 *
 * ## The two forms are not interchangeable, and v0.3.1 is where that got teeth
 *
 * `$violations` is the ADDRESS form — «`phrases[3].translation` — day.slot_outside_frame: …» — and
 * carries nothing the model wrote. The MESSAGE is the Russian prose, card texts and all, and it
 * goes to `fail_reason` for the plan screen and nowhere near a prompt.
 *
 * That split is the whole finding of the v0.3 run's second pass: a retry handed the previous
 * answer's sentences, each labelled with what was wrong with it, returned those sentences
 * (`docs/research/plan-v0.3-run.md`). A detailed account of a wrong answer works as a template.
 * So what travels to the next call is where to look, never what was there.
 */
final class PlanDayRefused extends RuntimeException
{
    /**
     * @param  list<string>  $violations  the ADDRESS form — {@see PlanViolation::address()}
     * @param  int  $repairCalls  P2R calls this run made before giving up: 0, or 1 when the answer
     *                            was nearly right and a repair was spent on it. The day row charges
     *                            them in a column of their own — an attempt is a DAY call, and
     *                            counting the repair as one made a two-call day read as three
     *                            (`docs/research/e2e-sim-1.md`, Д-18).
     */
    private function __construct(
        string $message,
        public readonly array $violations,
        public readonly int $repairCalls,
    ) {
        parent::__construct($message);
    }

    /** @param list<PlanViolation> $violations */
    public static function invalid(array $violations, int $repairCalls = 0): self
    {
        $prose = array_map(static fn (PlanViolation $v): string => (string) $v, $violations);
        $addresses = array_map(static fn (PlanViolation $v): string => $v->address(), $violations);

        return new self('День не прошёл валидатор: ' . implode('; ', $prose), $addresses, $repairCalls);
    }
}
