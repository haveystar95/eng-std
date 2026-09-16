<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * The knobs of the plan, read from `config/plan.php` once by the provider: how much a lesson
 * orders per level, how long a build may take before it counts as dead, the rescue kit, the
 * languages a plan may be built in, the seconds a card of each kind takes, and how many slot-judge
 * calls a learner has per day.
 */
final readonly class PlanConfig
{
    /**
     * @param  array<string, array{vocabulary: int, dialogue: int}>  $counts  by level
     * @param  list<array{text_target: string, text_native: string, pronunciation_native: string}>  $rescueKit
     * @param  list<string>  $languages  target language codes, in the order the entry screen offers them
     * @param  array<string, int>  $pace  seconds per card by kind value (`plan.pace`)
     * @param  int  $slotJudgeDailyCap  slot-judge model calls per learner per local day (`plan.slot_judge.daily_cap`)
     */
    public function __construct(
        public array $counts,
        public int $buildStaleSeconds,
        public array $rescueKit,
        public array $languages = ['en', 'de'],
        public array $pace = DayPace::DEFAULTS,
        public int $slotJudgeDailyCap = 60,
    ) {}

    /**
     * What a lesson orders at this level. The number of frames is not ordered: the model takes it from
     * the dialogue it writes (`lesson_day.v4.5`).
     *
     * @return array{vocabulary: int, dialogue: int}
     */
    public function countsFor(PlanLevel $level): array
    {
        return $this->counts[$level->value] ?? ['vocabulary' => 8, 'dialogue' => 8];
    }
}
