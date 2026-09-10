<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\DayCardsView;
use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\DayCardRepository;

final readonly class GetDayCardsHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private CardViews $views,
    ) {}

    public function __invoke(GetDayCards $query): DayCardsView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);
        $day = $plan->day($query->number);

        return new DayCardsView(
            planId: $plan->id()->value,
            dayId: $day->id()->value,
            number: $day->number(),
            status: $day->status()->value,
            cards: $this->views->forCards($this->cards->forDay($day->id()), $plan->targetLang()->value),
        );
    }
}
