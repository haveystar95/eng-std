<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/** What the lesson was ORDERED with — the counts, the languages and the learner's gender the rules compare against. */
final readonly class LessonValidationContext
{
    public function __construct(
        public int $vocabularyCount,
        public int $dialogueCount,
        public string $nativeLang,
        public string $targetLang,
        public ?VoiceGender $learnerGender = null,
    ) {}
}
