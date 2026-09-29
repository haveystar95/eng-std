<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * What the two stages' checks are given for a day (наряд GEN-4): the survival set, VOCABULARY_COUNT, the learner's gender,
 * the story so far and the learner's own words (наряд GEN-4c, {@see LessonRequests::learnerWords()}) for the skeleton; the
 * skeleton for the dialogue; and for both the pair of languages as their packs — by the
 * language CODES of the request (`ru`), never the names the prompts read («Russian»). A language with no pack gets an empty
 * one: the rules that need it do not run.
 */
final readonly class LessonContexts
{
    public function __construct(private LanguagePacks $packs) {}

    public function skeleton(LessonRequest $request): SkeletonContext
    {
        return new SkeletonContext(
            $request->survival,
            $request->vocabularyMin,
            $request->vocabularyMax,
            $this->native($request),
            $this->target($request),
            $request->learnerGender,
            $request->earlierDays,
            LessonRequests::learnerWords($request->topicDescription),
        );
    }

    public function dialogue(LessonRequest $request, Skeleton $skeleton): DialogueContext
    {
        return new DialogueContext($skeleton, $this->native($request), $this->target($request));
    }

    public function target(LessonRequest $request): LanguagePack
    {
        return $this->packs->for($request->targetLangCode !== '' ? $request->targetLangCode : $request->targetLanguage);
    }

    public function native(LessonRequest $request): LanguagePack
    {
        return $this->packs->for($request->nativeLangCode !== '' ? $request->nativeLangCode : $request->nativeLanguage);
    }
}
