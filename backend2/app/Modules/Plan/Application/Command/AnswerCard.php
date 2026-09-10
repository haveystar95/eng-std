<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The client's verdict on one card: passed, hinted, failed or skipped, and how many tries it took. */
final readonly class AnswerCard
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public DayCardId $cardId,
        public CardResult $result,
        public int $attempts,
        public UserId $actorId,
    ) {}
}
