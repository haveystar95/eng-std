<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * The knobs of the plan, read from `config/plan.php` once by the provider: how much a lesson
 * orders per level, how long a build may take before it counts as dead, the rescue kit, and the
 * languages a plan may be built in.
 */
final readonly class PlanConfig
{
    /**
     * @param  array<string, array{phrases: int, vocabulary: int, dialogue: int}>  $counts  by level
     * @param  list<array{text_target: string, text_native: string, pronunciation_native: string}>  $rescueKit
     * @param  list<string>  $languages  target language codes, in the order the entry screen offers them
     */
    public function __construct(
        public array $counts,
        public int $buildStaleSeconds,
        public array $rescueKit,
        public array $languages = ['en', 'de'],
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
