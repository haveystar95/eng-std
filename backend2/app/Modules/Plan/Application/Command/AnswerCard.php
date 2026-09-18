<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The client's verdict on one card: passed, hinted, failed or skipped, how many tries it took — and what the answer
 * left behind (`response`: what was heard, the slot's value, the mode, the hint's moment; наряд SESSION-1a, D-30),
 * null when it left nothing.
 *
 * A card that carries a CHOICE beside its own answer (`dialogue_ask`, наряд BACK-TAILS-1, доработка §1) brings the id
 * of the option chosen as well — the two halves of one screen answered together; null on every other kind and on a
 * card dealt without its check.
 */
final readonly class AnswerCard
{
    /** @param array<string, mixed>|null $response */
    public function __construct(
        public PlanId $planId,
        public int $number,
        public DayCardId $cardId,
        public CardResult $result,
        public int $attempts,
        public UserId $actorId,
        public ?array $response = null,
        public ?string $choice = null,
    ) {}
}
