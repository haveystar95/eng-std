<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * The inputs of the lesson prompt, exactly as its INPUTS section names them — plus the two
 * language CODES beside the names: the prompt reads «Russian», the script check reads «ru».
 */
final readonly class LessonRequest
{
    /** @param list<string> $previousViolations */
    public function __construct(
        public string $topic,
        public string $topicDescription,
        public string $targetLanguage,
        public string $nativeLanguage,
        public PlanLevel $level,
        public int $phrasesCount,
        public int $vocabularyCount,
        public int $dialogueCount,
        public array $previousViolations = [],
        public string $targetLangCode = '',
        public string $nativeLangCode = '',
    ) {}

    /** @param list<string> $violations */
    public function withViolations(array $violations): self
    {
        return new self(
            $this->topic, $this->topicDescription, $this->targetLanguage, $this->nativeLanguage, $this->level,
            $this->phrasesCount, $this->vocabularyCount, $this->dialogueCount, $violations,
            $this->targetLangCode, $this->nativeLangCode,
        );
    }
}
