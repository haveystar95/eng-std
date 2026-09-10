<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * The knobs of the plan, read from `config/plan.php` once by the provider: how much a lesson
 * orders per level, how long a build may take before it counts as dead, and the rescue kit.
 */
final readonly class PlanConfig
{
    /**
     * @param  array<string, array{phrases: int, vocabulary: int, dialogue: int}>  $counts  by level
     * @param  list<array{text_target: string, text_native: string, pronunciation_native: string}>  $rescueKit
     */
    public function __construct(
        public array $counts,
        public int $buildStaleSeconds,
        public array $rescueKit,
    ) {}

    /** @return array{phrases: int, vocabulary: int, dialogue: int} */
    public function countsFor(PlanLevel $level): array
    {
        $row = $this->counts[$level->value] ?? ['phrases' => 6, 'vocabulary' => 8, 'dialogue' => 8];
        // PHRASES_COUNT is never larger than DIALOGUE_COUNT — the prompt's own rule.
        $row['phrases'] = min($row['phrases'], $row['dialogue']);

        return $row;
    }
}
