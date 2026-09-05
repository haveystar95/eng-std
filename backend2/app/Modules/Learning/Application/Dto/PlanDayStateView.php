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
    ) {}
}
