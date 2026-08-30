<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * ONE DAY, judged against THE REST OF THE PLAN.
 *
 * {@see PlanDayCandidate} carries what a day needs to be judged on its own; this carries what it
 * needs to be judged as part of a sequence. They are separate types because they are separate
 * questions with separate failure modes: a day can be perfectly built and still re-teach yesterday's
 * words, and a day can be internally broken while sitting correctly in its plan.
 */
final readonly class PlanCoherenceCandidate
{
    /**
     * @param  list<PlanDayItem>  $items                the day's material, as the model returned it
     * @param  array<string, string>  $knownTexts       term id => text, met on an EARLIER day of this
     *                                                  plan. The same map the prompt's KNOWN block is
     *                                                  built from, so the gate and the instruction
     *                                                  cannot disagree about what «already met» means.
     * @param  list<string>  $previousCheckpoints       every checkpoint the plan's other days already
     *                                                  promise
     * @param  list<string>  $dayCheckpoints            this day's own checkpoints
     * @param  list<array{name: string, gender: string, number: string, note: string}>  $entities
     *                                                  the skeleton's binding list: who and what this
     *                                                  plan is about, and how they agree
     */
    public function __construct(
        public string $supportLang,
        public int $dayIndex,
        public array $items,
        public array $knownTexts,
        public array $previousCheckpoints,
        public array $dayCheckpoints,
        public array $entities,
    ) {}
}
