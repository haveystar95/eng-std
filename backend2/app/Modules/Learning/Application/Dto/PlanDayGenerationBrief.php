<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Everything the day generator needs, handed over the module boundary as primitives.
 *
 * Assembled inside the transaction that CLAIMED the day, so what the generator is holding is the
 * day it owns and not a snapshot of a day somebody else has since taken.
 */
final readonly class PlanDayGenerationBrief
{
    /**
     * @param  list<string>  $checkpoints  what has to be heard on this day, in order
     * @param  list<string>  $previousCheckpoints  what every OTHER day of this plan already promises
     *        — the coherence gate's input ({@see \App\Modules\Generation\Domain\Service\PlanCoherenceValidator}).
     *        Two days promising the same line is not a harmless repetition: the conversation ticks
     *        checkpoints off, and the same one ticked twice reads as two abilities earned.
     * @param  list<string>  $goalTerms    verbatim in both languages, never translated
     * @param  list<array{name: string, gender: string, number: string, note: string}>  $entities
     * @param  list<string>  $constraints
     * @param  list<string>  $openingLines  what the day's interlocutors actually say, verbatim
     *        from the skeleton — the lines P2 may quote as the ones the learner must recognise, and
     *        the list the validator checks a `speaker: role` line against.
     * @param  array<string, mixed>  $dayJson  the day as the prompt reads it
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
        public int $phraseCount,
        public int $chunkCount,
        public int $wordCount,
        public array $checkpoints,
        public array $entities,
        public array $constraints,
        public array $goalTerms,
        public array $dayJson,
        public array $openingLines = [],
        public array $previousCheckpoints = [],
    ) {}
}
