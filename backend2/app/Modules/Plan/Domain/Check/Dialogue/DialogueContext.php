<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * What the dialogue was ORDERED with (`lesson_dialogue.v1.1`, INPUTS): the skeleton it was written from — every frame and every
 * partner line it may say, and no other — DIALOGUE_COUNT as the server counted it off that skeleton, and the pair of
 * languages as their packs.
 */
final readonly class DialogueContext
{
    public int $dialogueCount;

    public function __construct(
        public Skeleton $skeleton,
        public LanguagePack $native,
        public LanguagePack $target,
    ) {
        $this->dialogueCount = $skeleton->dialogueCount();
    }

    public function targetWords(): LanguageWords
    {
        return new LanguageWords($this->target);
    }

    public function nativeWords(): LanguageWords
    {
        return new LanguageWords($this->native);
    }

    /** The learner's language's word rules when its pack writes every key named, else null (see {@see SkeletonContext::targetReading()}). */
    public function nativeReading(string ...$keys): ?LanguageWords
    {
        return array_filter($keys, fn (string $key): bool => ! $this->native->has($key)) === [] ? $this->nativeWords() : null;
    }
}
