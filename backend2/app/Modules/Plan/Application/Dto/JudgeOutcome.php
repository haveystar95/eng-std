<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Entity\DayCard;

/**
 * What one judged attempt left (наряд SESSION-1a, разд. 4): the card as it stands after it — answered when accepted,
 * one attempt more when not — the ruling, and what the server judged: `heard` as it arrived (наряд CONV-2, п. 8 — the
 * client prints «услышал: …» under a refusal, so a learner can tell a recogniser that misheard from a judge that
 * misjudged). The plan's target language and its day numbers ride along for the card view (the audio's voice,
 * `source_day`), the way an answer's outcome carries them, instead of loading the plan again.
 */
final readonly class JudgeOutcome
{
    /** @param array<string, int> $dayNumbers day id → number, of the plan's days */
    public function __construct(
        public DayCard $card,
        public SlotJudgeVerdict $verdict,
        public string $targetLang,
        public array $dayNumbers,
        public string $heard = '',
    ) {}
}
