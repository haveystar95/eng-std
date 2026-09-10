<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Exception\StageIncomplete;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\DayStatus;

/**
 * A stage is «closed» by being answered through — there is no stage row to flip, only the
 * cards. This handler is the check: it refuses while a card of the stage is still unanswered,
 * so the client cannot skip ahead by declaring.
 */
final readonly class CloseStageHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
    ) {}

    public function __invoke(CloseStage $command): void
    {
        $plan = $this->access->owned($command->planId, $command->actorId);
        $day = $plan->day($command->number);
        if ($day->status() !== DayStatus::InProgress) {
            throw PlanDayNotOpen::day($command->number, $day->status());
        }

        $remaining = count(array_filter(
            $this->cards->forDay($day->id()),
            static fn (DayCard $c): bool => $c->stage() === $command->stage && ! $c->isAnswered(),
        ));
        if ($remaining > 0) {
            throw StageIncomplete::stage($command->stage, $remaining);
        }
    }
}
