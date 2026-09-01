<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Service\PlanStageLadder;

/**
 * One term of a plan day, as much of it as the ORDER needs to know.
 *
 * `isLine` used to be the whole of it, and a two-way split is not enough to lay a day out: a
 * connector is neither a word nor a reply, and the interlocutor's own line is a reply the learner
 * will never say. Both are now told apart — see {@see \App\Modules\Learning\Domain\Service\PlanDayOrder}.
 */
final readonly class PlanDayCard
{
    public function __construct(
        public string $termId,
        /** What this card DOES in its day — `word`, `chunk` or `line`. {@see PlanStageLadder} */
        public string $kind,
        /** The interlocutor's own line: understood, never produced, and dealt last. */
        public bool $isRoleLine = false,
        /** {@see \App\Modules\Shared\Domain\Service\DifficultyScorer}; null = never scored. */
        public ?int $difficultyScore = null,
    ) {}

    public function isLine(): bool
    {
        return $this->kind === PlanStageLadder::KIND_LINE;
    }
}
