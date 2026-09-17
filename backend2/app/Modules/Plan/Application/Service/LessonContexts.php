<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonValidationContext;

/**
 * What the validator is given for a lesson (наряды GEN-2b, GEN-3): the counts it was ordered with, the learner's gender,
 * the pair of languages as their packs — by the language CODES of the request (`ru`), never the names the prompt reads
 * («Russian») — and the story so far with the scene's partner role. A language with no pack gets an empty one: its checks
 * are skipped and counted.
 */
final readonly class LessonContexts
{
    public function __construct(private LanguagePacks $packs) {}

    public function of(LessonRequest $request): LessonValidationContext
    {
        return new LessonValidationContext(
            $request->vocabularyCount,
            $request->dialogueCount,
            $this->packs->for($request->nativeLangCode !== '' ? $request->nativeLangCode : $request->nativeLanguage),
            $this->packs->for($request->targetLangCode !== '' ? $request->targetLangCode : $request->targetLanguage),
            $request->learnerGender,
            $request->earlierDays,
            $request->roles->partnerTarget,
        );
    }
}
