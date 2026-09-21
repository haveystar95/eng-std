<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Exception\StageIncomplete;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\Stage;

/**
 * A stage is «closed» by being answered through — there is no stage row to flip, only the
 * cards. This handler is the check: it refuses while a card of the stage is still unanswered,
 * so the client cannot skip ahead by declaring.
 *
 * THE SIXTH STAGE HAS NO CARDS (наряд CONV-1), and it is checked the same way against the only
 * journal it has: it is closed when the journal of stages says it was walked (наряд CONV-2, п. 2), and
 * a declaration that it is closed while the role is still waiting is refused like any other.
 */
final readonly class CloseStageHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private StagePassageRepository $passages,
    ) {}

    public function __invoke(CloseStage $command): void
    {
        $plan = $this->access->owned($command->planId, $command->actorId);
        $day = $plan->day($command->number);
        if ($day->status() !== DayStatus::InProgress) {
            throw PlanDayNotOpen::day($command->number, $day->status());
        }

        if ($command->stage === Stage::Conversation) {
            if ($day->hasConversation() && $this->passages->of($day->id(), Stage::Conversation) === null) {
                throw StageIncomplete::stage(Stage::Conversation, 1);
            }

            return;
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
