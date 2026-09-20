<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «Начать разговор» / «Продолжить» / «Ещё раз» (кадры 37-5, 37-12) — one call for all three: the day
 * has one open talk at a time, `again` closes it and opens a new one, and `hints` is the «Без
 * подсказок» switch of the entry card, fixed for the talk it starts.
 */
final readonly class StartConversation
{
    public function __construct(
        public PlanId $planId,
        public int $number,
        public UserId $actorId,
        public bool $again = false,
        public bool $hints = true,
    ) {}
}
