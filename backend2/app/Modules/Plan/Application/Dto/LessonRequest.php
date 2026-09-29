<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE INPUTS OF A DAY (наряд GEN-4) — what the skeleton (`lesson_skeleton.v1`, INPUTS) and the dialogue
 * (`lesson_dialogue.v1`, INPUTS) are written from, exactly as their prompts name them — plus the two language CODES beside the
 * names (the prompts read «Russian», the checks read «ru») and the scene's id, which seeds the shuffle of the options.
 *
 * `topicDescription` is the scene's three-line brief with the learner's own words after it; `survival` is the scene's
 * SURVIVAL_SET; `learnerGender` null is «unknown»; `roles` are LEARNER_ROLE (the plan's) and PARTNER_ROLE (the scene's);
 * `vocabularyMin`–`vocabularyMax` is VOCABULARY_COUNT; `earlierDays` is EARLIER_DAYS — the story so far, empty on the first
 * day. `previousViolations` — what the stage's previous answer was refused for, quoted as data on its one repeat. Built by
 * {@see \App\Modules\Plan\Application\Service\LessonRequests}.
 */
final readonly class LessonRequest
{
    /** @param list<string> $previousViolations */
    public function __construct(
        public string $topic,
        public string $topicDescription,
        public SurvivalSet $survival,
        public string $targetLanguage,
        public string $nativeLanguage,
        public PlanLevel $level,
        public ?VoiceGender $learnerGender,
        public int $vocabularyMin,
        public int $vocabularyMax,
        public LessonRoles $roles,
        public EarlierDays $earlierDays,
        public string $targetLangCode = '',
        public string $nativeLangCode = '',
        public string $sceneId = '',
        public array $previousViolations = [],
    ) {}

    /** @param list<string> $violations */
    public function withViolations(array $violations): self
    {
        return new self(
            $this->topic, $this->topicDescription, $this->survival, $this->targetLanguage, $this->nativeLanguage, $this->level,
            $this->learnerGender, $this->vocabularyMin, $this->vocabularyMax, $this->roles, $this->earlierDays,
            $this->targetLangCode, $this->nativeLangCode, $this->sceneId, $violations,
        );
    }

    /** What LEARNER_GENDER says to the prompts. */
    public function learnerGenderInput(): string
    {
        return $this->learnerGender->value ?? 'unknown';
    }

    /** What VOCABULARY_COUNT says to the skeleton: the range, «8–12». */
    public function vocabularyCountInput(): string
    {
        return $this->vocabularyMin.'–'.$this->vocabularyMax;
    }
}
