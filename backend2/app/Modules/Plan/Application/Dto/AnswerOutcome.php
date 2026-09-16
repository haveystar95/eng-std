<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;

/**
 * What one answer left (наряд SESSION-1a, D-02): the answered card, and the card dealt again at the end of the stage
 * when the answer was a first failure; whether the unit now comes back and on which day; the day's numbers refolded
 * over its cards and the minutes of the card's stage — everything a stage's and a day's summary is written from, so
 * the client counts nothing itself. The plan's target language and its day numbers ride along for the card views
 * (the audio's voice, `source_day`), read in the same transaction instead of loading the plan again.
 */
final readonly class AnswerOutcome
{
    /** @param array<string, int> $dayNumbers day id → number, of the plan's days */
    public function __construct(
        public DayCard $card,
        public ?DayCard $requeued,
        public bool $unitReturns,
        public ?int $returnsDay,
        public DayMetrics $metrics,
        public int $stageMinutes,
        public string $targetLang,
        public array $dayNumbers,
    ) {}
}
