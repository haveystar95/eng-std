<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * What the skeleton was ORDERED with (`lesson_skeleton.v1.1`, INPUTS): the scene's survival set, VOCABULARY_COUNT, the pair of
 * languages as their packs, the learner's gender (null — unknown), the days of the plan already written (EARLIER_DAYS) and
 * the learner's own words (наряд GEN-4c) — what TOPIC_DESCRIPTION gives as «About the learner, in their own words»: the plan's
 * goal as the learner wrote it, the only details of the learner the skeleton is given ('' — none).
 */
final readonly class SkeletonContext
{
    public function __construct(
        public SurvivalSet $survival,
        public int $vocabularyMin,
        public int $vocabularyMax,
        public LanguagePack $native,
        public LanguagePack $target,
        public ?VoiceGender $learnerGender = null,
        public EarlierDays $earlierDays = new EarlierDays,
        public string $learnerWords = '',
    ) {}

    public function targetWords(): LanguageWords
    {
        return new LanguageWords($this->target);
    }

    /**
     * The target's word rules when its pack writes every key named, else null — a rule that needs them does not run for a
     * language nobody has written a pack for, and never borrows another's words.
     */
    public function targetReading(string ...$keys): ?LanguageWords
    {
        return self::reads($this->target, array_values($keys)) ? $this->targetWords() : null;
    }

    /** The learner's language's word rules when its pack writes every key named, else null. */
    public function nativeReading(string ...$keys): ?LanguageWords
    {
        return self::reads($this->native, array_values($keys)) ? $this->nativeWords() : null;
    }

    /** @param list<string> $keys */
    private static function reads(LanguagePack $pack, array $keys): bool
    {
        return array_filter($keys, static fn (string $key): bool => ! $pack->has($key)) === [];
    }

    public function nativeWords(): LanguageWords
    {
        return new LanguageWords($this->native);
    }
}
