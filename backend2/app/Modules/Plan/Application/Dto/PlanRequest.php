<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/** The inputs of the plan builder prompt (`plan-builder-v2.1`), exactly as its INPUTS section names them. */
final readonly class PlanRequest
{
    /**
     * @param  list<array{title: string, must_say: list<string>}>  $existingScenes  the scenes already in the plan (an
     *                                                                             extension): each its title and its `must_say` items as the prompt reads them; else empty
     * @param  list<string>  $previousViolations
     */
    public function __construct(
        public string $goal,
        public string $targetLanguage,
        public string $nativeLanguage,
        public PlanLevel $level,
        public int $scenesCount,
        public array $existingScenes = [],
        /** What the previous attempt was refused for — quoted back as data on the one retry. */
        public array $previousViolations = [],
    ) {}

    /** @param list<string> $violations */
    public function withViolations(array $violations): self
    {
        return new self($this->goal, $this->targetLanguage, $this->nativeLanguage, $this->level, $this->scenesCount, $this->existingScenes, $violations);
    }
}
