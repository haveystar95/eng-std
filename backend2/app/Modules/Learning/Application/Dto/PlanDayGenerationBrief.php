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
     * @param  list<string>  $goalTerms    verbatim in both languages, never translated
     * @param  list<array{name: string, gender: string, number: string, note: string}>  $entities
     * @param  list<string>  $constraints
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
        public int $wordCount,
        public array $checkpoints,
        public array $entities,
        public array $constraints,
        public array $goalTerms,
        public array $dayJson,
    ) {}
}
