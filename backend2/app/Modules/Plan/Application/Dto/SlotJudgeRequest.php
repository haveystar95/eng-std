<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE ATTEMPT PUT TO THE SLOT JUDGE (`slot_judge.v3`, наряд SESSION-1a, разд. 4; наряд CONV-2, пп. 7–8): the prompt's
 * INPUT, field for field, with the values as they are — what kind of attempt it is, the languages by name, the level, the
 * partner's line, the frame with its slot, what kind of value the slot takes and the values the lesson used, and what the
 * recogniser heard.
 *
 * TWO KINDS OF ATTEMPT, and the judge must know which (`mode`):
 * - `answer` — «Ответь своими словами» (`speak_answer`): the learner answers PARTNER_LINE in their own words; the pattern
 *   is one way to say it and is not required word for word — «Yes, it is my first visit» answers «Is this your first
 *   visit here?» whatever the frame says;
 * - `own_value` — the last round of «Скажи целиком»: the pattern WAS said, and only the learner's own value in its
 *   window is judged — a value of the kind the slot asks for, which does not have to be what PARTNER_LINE mentions.
 */
final readonly class SlotJudgeRequest
{
    public const MODE_ANSWER = 'answer';

    public const MODE_OWN_VALUE = 'own_value';

    /**
     * @param  'answer'|'own_value'  $mode
     * @param  string  $exampleValues  the target values of the frame's fillers, joined by «; »
     * @param  string  $heard  the recogniser's text as it came — never collapsed or trimmed
     */
    public function __construct(
        public string $mode,
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
