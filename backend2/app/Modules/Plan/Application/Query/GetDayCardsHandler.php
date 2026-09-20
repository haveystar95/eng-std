<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\DayCardsView;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Repository\DayCardRepository;

/**
 * The whole day's cards in walking order, each in the registry's envelope (наряд SESSION-1a, D-03, D-04), and the
 * target language's speech rules beside them (наряд FIX-2, п. 2) — the lists the phone compares a spoken attempt by,
 * so that its verdict and the server's are one rule read twice and not two rules.
 */
final readonly class GetDayCardsHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private CardViews $views,
        private LanguagePacks $packs,
        private PlanConfig $config,
    ) {}

    public function __invoke(GetDayCards $query): DayCardsView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);
        $day = $plan->day($query->number);
        $dayNumbers = [];
        foreach ($plan->days() as $planDay) {
            $dayNumbers[$planDay->id()->value] = $planDay->number();
        }

        return new DayCardsView(
            planId: $plan->id()->value,
            dayId: $day->id()->value,
            number: $day->number(),
            status: $day->status()->value,
            cards: $this->views->forCards($this->cards->forDay($day->id()), $plan->targetLang()->value, $dayNumbers),
            speech: $this->packs->for($plan->targetLang()->value)->speech(),
            repeatMisses: $this->config->repeatMisses,
        );
    }
}
