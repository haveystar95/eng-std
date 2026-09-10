<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

/** What was ORDERED from the model — the counts and the languages the checks compare against. */
final readonly class LessonContext
{
    public function __construct(
        public int $phrasesCount,
        public int $vocabularyCount,
        public int $dialogueCount,
        public string $nativeLang,
        public string $targetLang,
    ) {}
}
