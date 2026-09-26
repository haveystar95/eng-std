<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * The knobs of the plan, read from `config/plan.php` once by the provider: how much a lesson
 * orders per level, how long a build may take before it counts as dead, the
 * languages a plan may be built in, the seconds a card of each kind takes, how long «Фразы» may take before it
 * cuts itself, how many slot-judge calls a learner has per day, and how much of a line on the screen may go
 * missing when it is said aloud.
 */
final readonly class PlanConfig
{
    /**
     * @param  array<string, array{vocabulary: int, dialogue: int}>  $counts  by level
     * @param  list<string>  $languages  the EFFECTIVE plan targets — `LanguageRoles::planTargets()` narrowed by
     *                                  `plan.languages` (наряд LANG-1 §7), in the order the entry screen offers
     *                                  them; what `GET /plans/languages` lists and `POST /plans` accepts
     * @param  array<string, int>  $pace  seconds per card by kind value (`plan.pace`)
     * @param  int  $phrasesBudget  seconds «Фразы» may take before the trimming ladder runs (`plan.phrases_budget`)
     * @param  int  $slotJudgeDailyCap  slot-judge model calls per learner per local day (`plan.slot_judge.daily_cap`)
     * @param  int  $repeatMisses  content words a line ON THE SCREEN may lose and still pass (`plan.speech.repeat_misses`)
     */
    public function __construct(
        public array $counts,
        public int $buildStaleSeconds,
        // No default: a copy of the list here is the second place it would have to be kept in (п. 145).
        public array $languages,
        public array $pace = DayPace::DEFAULTS,
        public int $phrasesBudget = PhrasesStage::BUDGET,
        public int $slotJudgeDailyCap = 60,
        public int $repeatMisses = 0,
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
