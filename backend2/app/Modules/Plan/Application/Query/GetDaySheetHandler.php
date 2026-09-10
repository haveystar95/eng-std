<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\SheetView;
use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\TermKind;

final readonly class GetDaySheetHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanTermRepository $terms,
    ) {}

    public function __invoke(GetDaySheet $query): SheetView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);
        $day = $plan->day($query->number);
        $scene = $plan->sceneOf($day);
        $terms = $scene === null ? [] : $this->terms->forScene($scene->id());

        return new SheetView(
            planId: $plan->id()->value,
            number: $day->number(),
            words: array_values(array_map(CardViews::term(...), array_filter($terms, static fn (PlanTerm $t): bool => $t->kind() !== TermKind::Phrase))),
            phrases: array_values(array_map(CardViews::term(...), array_filter($terms, static fn (PlanTerm $t): bool => $t->kind() === TermKind::Phrase))),
        );
    }
}
