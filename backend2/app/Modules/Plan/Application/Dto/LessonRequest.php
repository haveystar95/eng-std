<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The inputs of the lesson prompt (`lesson_day.v4.5`), exactly as its INPUTS section names them —
 * plus the two language CODES beside the names: the prompt reads «Russian», the validator reads «ru».
 *
 * `topicDescription` is the scene's brief with the learner's own facts after it (their goal in their
 * words), so a fact that fits a frame's slot becomes a filler. `learnerGender` null is «unknown».
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
        public ?VoiceGender $learnerGender,
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
            $this->learnerGender, $this->vocabularyCount, $this->dialogueCount, $violations,
            $this->targetLangCode, $this->nativeLangCode,
        );
    }

    /** What LEARNER_GENDER says to the prompt. */
    public function learnerGenderInput(): string
    {
        return $this->learnerGender->value ?? 'unknown';
    }
}
