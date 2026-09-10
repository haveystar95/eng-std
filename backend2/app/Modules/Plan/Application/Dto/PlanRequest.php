<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/** The inputs of the plan builder prompt, exactly as its INPUTS section names them. */
final readonly class PlanRequest
{
    /**
     * @param  list<string>  $existingScenes  titles of the scenes already in the plan (an extension), else empty
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
}
