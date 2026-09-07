<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\PlanDayState;

/** The state of one day and what it still costs — {@see \App\Modules\Learning\Application\Service\PlanDayStateCensus}. */
final readonly class PlanDayStateView
{
    public function __construct(
        public PlanDayState $state,
        /** Cards the day still deals, as the planner would deal them. Zero once the day is done. */
        public int $cardsLeft,
        /** «около N минут» — cards × seconds, rounded up to a whole minute. */
        public int $minutesLeft,
        /**
         * THE TWO SITTINGS, PRICED APART (наряд DAY-FIX-3, Ч.4.3 / Ч.5.1): «Материал» and
         * «Разговор», cards and minutes each. The day screen prints both — «материал около 12
         * минут · разговор около 6», — and the plan tab prints the one still ahead.
         */
        public int $materialCards = 0,
        public int $materialMinutes = 0,
        public int $conversationCards = 0,
        public int $conversationMinutes = 0,
    ) {}
}
