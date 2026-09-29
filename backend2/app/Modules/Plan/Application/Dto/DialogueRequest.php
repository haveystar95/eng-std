<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * THE INPUTS OF THE DIALOGUE (`lesson_dialogue.v1.1`, INPUTS; наряд GEN-4, 3.5): the day's inputs, the skeleton as it came out of
 * its check and its repairs, and DIALOGUE_COUNT — counted off that skeleton ({@see Skeleton::dialogueCount()}).
 * `previousViolations` — what the dialogue's previous answer was refused for, quoted as data on its one repeat.
 */
final readonly class DialogueRequest
{
    public int $dialogueCount;

    /** @param list<string> $previousViolations */
    public function __construct(
        public LessonRequest $lesson,
        public Skeleton $skeleton,
        public array $previousViolations = [],
    ) {
        $this->dialogueCount = $skeleton->dialogueCount();
    }

    /** @param list<string> $violations */
    public function withViolations(array $violations): self
    {
        return new self($this->lesson, $this->skeleton, $violations);
    }
}
