<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE ATTEMPT PUT TO THE SLOT JUDGE (`slot_judge.v2`, наряд SESSION-1a, разд. 4): the prompt's INPUT, field for field,
 * with the values as they are — the languages by name, the level, the partner's line the learner answers, the frame
 * with its slot, what kind of value the slot takes and the values the lesson used, and what the recogniser heard.
 *
 * One task only, the slot: the retelling the judge used to rule on was taken off it with `speak_retell` itself (наряд
 * BACK-TAILS-1 §1.1).
 */
final readonly class SlotJudgeRequest
{
    /**
     * @param  string  $exampleValues  the target values of the frame's fillers, joined by «; »
     * @param  string  $heard  the recogniser's text as it came — never collapsed or trimmed
     */
    public function __construct(
        public string $targetLanguage,
        public string $nativeLanguage,
        public string $level,
        public string $partnerLine,
        public string $partnerLineNative,
        public string $pattern,
        public string $patternNative,
        public string $slotHint,
        public string $exampleValues,
        public string $heard,
    ) {}
}
