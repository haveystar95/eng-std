<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * One attempt at a card judged by meaning (`…/judge`, наряд SESSION-1a, разд. 4): what the recogniser heard — empty for
 * silence — and whether the frame was on screen before it («Подсказать» or five seconds of silence).
 */
final readonly class JudgeCard
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public DayCardId $cardId,
        public string $heard,
        public bool $hinted,
        public UserId $actorId,
    ) {}
}
