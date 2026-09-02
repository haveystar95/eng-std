<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE ability the plan promises — «ты сможешь: сказать, где именно болит» — with the checkpoint
 * that proves it and the price of teaching it.
 *
 * The unit the SERVER schedules with, and since v0.2 the unit the model PRICES. That is the whole
 * difference between this type today and the one PLAN-1a shipped. It used to be an artefact of
 * division: P1 priced a DAY, and `estTerms` was that day's budget shared out over its outcome
 * lines — so the sum of the prices was, necessarily, the budget the server had handed the model one
 * call earlier. Every judgement built on that sum was a judgement about the server's own input.
 *
 * Now P1 answers with `est_terms` per skill, 3–8, and the sum is a fact about the goal instead of
 * an echo. {@see \App\Modules\Learning\Domain\Service\PlanScheduler} is the only thing that reads
 * it, and it reads it to decide how many days there are — which is the question the old shape could
 * not be asked.
 */
final readonly class PlanSkill
{
    /** @param list<string> $topics */
    public function __construct(
        /**
         * THE ID EVERY CARD OF THE DAY POINTS AT — «s1.2».
         *
         * New in v0.4 and the mechanical half of «каждая карточка отвечает, почему она здесь»
         * (канон §8): a card names one skill in `skill_ref`, and a card that names none — or names
         * one this scene never promised — is refused
         * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::SKILL_REF_INVALID}).
         *
         * The model is asked for it and the SERVER fills it in when the answer omits one
         * ({@see \App\Modules\Learning\Domain\ValueObject\PlanOutline::fromArray()}): an id is
         * an address, and refusing a whole skeleton over a missing address would be paying for a
         * second call to get a string this code can write itself.
         */
        public string $id,
        /** The ability, in the learner's own language, as P1 wrote it. */
        public string $outcome,
        /** What must be HEARD for it to count. Exactly one per skill — never null since v0.2. */
        public string $checkpoint,
        /** How many new cards this ability costs, as the model priced it. 3–8; always ≥ 1. */
        public int $estTerms,
        /** Which scene it belongs to — the source of its role, its title and its conversation. */
        public int $sceneIndex,
        /** Where it sits inside its scene, 0-based. */
        public int $skillIndex,
        /**
         * P1's order across the whole plan, 0-based — and therefore the PRIORITY.
         *
         * The prompt states it plainly: scenes and skills are ordered by dependency and by
         * likelihood, and the server cuts from the tail. So this is the sequence the scheduler
         * truncates, and it is carried on the skill rather than recomputed from position in an
         * array, because a dropped skill has to keep saying where it stood.
         */
        public int $position,
        /** The AREAS this skill's substitution words come from — never words. */
        public array $topics = [],
    ) {}
}
