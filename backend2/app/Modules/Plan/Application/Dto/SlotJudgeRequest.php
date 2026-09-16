<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE ATTEMPT PUT TO THE SLOT JUDGE (`slot_judge.v1`, наряд SESSION-1a, разд. 4): the prompt's INPUT, field for field,
 * with the values as they are — the languages by name, the level, the partner's line the learner answers, the frame
 * with its slot, what kind of value the slot takes and the values the lesson used, and what the recogniser heard.
 *
 * For a retelling (`TASK=retell`) the frame, the hint and the examples are empty: there is no slot, only the
 * partner's line and the learner's native retelling of it.
 */
final readonly class SlotJudgeRequest
{
    public const TASK_ANSWER = 'answer';

    public const TASK_RETELL = 'retell';

    /**
     * @param  'answer'|'retell'  $task
     * @param  string  $exampleValues  the target values of the frame's fillers, joined by «; »
     * @param  string  $heard  the recogniser's text as it came — never collapsed or trimmed
     */
    public function __construct(
        public string $task,
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
