<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * ONE DAY-SCENE, handed over the module boundary as primitives.
 *
 * Assembled inside the transaction that CLAIMED the day, so what the generator holds is the day it
 * owns and not a snapshot of a day somebody else has since taken.
 *
 * ## v0.4: a scene in, and no arithmetic at all
 *
 * The three exact counts are gone (`phraseCount`, `wordCount`, `chunkCount`) and so are the
 * numbered checkpoints. A day is ONE SCENE — «день = одна сцена целиком» (канон §2) — and what the
 * prompt is handed is that scene: its title, its вводка, its skills WITH IDS, the lines the other
 * person opens with, and the names of the scenario. The sizes of the shelves are the prompt's own
 * guidance and the validator's counters; nothing here computes them, because there is no longer a
 * number for a day to be «one card off».
 *
 * `termBudget` survives as ONE number and it is not a demand: it is what the scheduler wrote into
 * the day when the plan was made ({@see \App\Modules\Learning\Domain\Service\SceneDay::UNITS}), and
 * it is read by the ledger row and by the readiness denominator, neither of which is the model's
 * business.
 */
final readonly class PlanDayGenerationBrief
{
    /**
     * @param  list<array{id: string, outcome: string, checkpoint: string, topics: list<string>}>  $skills
     *         the abilities of THIS scene, each with the id every card of the day must name in
     *         `skill_ref` ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::SKILL_REF_INVALID})
     * @param  list<string>  $openingLines  what the other person actually says in this scene — raw
     *         material for the «Тебе скажут» shelf. v0.4 asks the model to ADAPT them rather than
     *         quote them, so nothing is compared against this list any more; it is still handed in
     *         because a shelf written without it is a conversation with somebody else.
     * @param  list<string>  $entities      proper names of the scenario — filler, never cards
     * @param  list<string>  $goalTerms     Latin-alphabet names the learner typed themselves
     * @param  list<string>  $previousViolations  where the LAST answer broke, as addresses. Empty on
     *         a first run, and never a quotation of what that answer wrote
     *         ({@see \App\Modules\Generation\Domain\ValueObject\PlanViolation::address()}).
     */
    public function __construct(
        public string $planId,
        public string $userId,
        public string $planDayId,
        public int $dayIndex,
        public string $planTitle,
        public string $goalText,
        public string $dayTitle,
        public string $supportLang,
        public string $targetLang,
        public string $level,
        public int $termBudget,
        public string $sceneTitle,
        /** The вводка, 2–3 sentences in the support language. Already written; the day must not retell it. */
        public string $sceneIntro,
        public array $skills,
        public array $openingLines = [],
        public array $entities = [],
        public array $goalTerms = [],
        public array $previousViolations = [],
        /**
         * `understanding` | `speaking` | `''` — the outcome of the entry's listening step.
         *
         * P2 v0.4.1 reads it as `{{balance}}` and tilts two shelves toward opposite ends of their
         * guides. An empty string is the ordinary case (the step is optional and most plans skip
         * it), and the prompt's own rule says to ignore the line then — so the empty value is the
         * contract rather than a missing one.
         *
         * It rides on the DAY brief and not just on the plan because every day of the plan is
         * written against it: the decision was made once, at the entry, and it is the same decision
         * on day 4 as on day 1.
         */
        public string $balance = '',
    ) {}

    /**
     * The scene as the PROMPT reads it — the whole of `{{scene}}`, assembled by the server.
     *
     * One method rather than a field, so the JSON the model sees and the facts the gates judge
     * against cannot come apart: both are built from the same six properties above.
     *
     * @return array<string, mixed>
     */
    public function sceneJson(): array
    {
        return [
            'title' => $this->sceneTitle,
            'intro' => $this->sceneIntro,
            'skills' => $this->skills,
            'opening_lines' => $this->openingLines,
            'entities' => $this->entities,
        ];
    }

    /**
     * The ids of this scene's skills — what `skill_ref` is checked against.
     *
     * @return list<string>
     */
    public function skillIds(): array
    {
        $out = [];
        foreach ($this->skills as $skill) {
            $id = trim($skill['id']);
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return $out;
    }
}
