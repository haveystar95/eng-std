<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\AnswerOutcome;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Exception\CardNotFound;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Records one answer. A first failure deals the same card again at the end of its stage; a
 * second one marks the unit to return on the next content day. The card is locked for the
 * transaction so a replayed answer is refused rather than counted twice.
 */
final readonly class AnswerCardHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(AnswerCard $command): AnswerOutcome
    {
        $now = $this->clock->now();

        return $this->tx->run(function () use ($command, $now): AnswerOutcome {
            $plan = $this->access->owned($command->planId, $command->actorId);
            $day = $plan->day($command->number);
            if ($day->status() !== DayStatus::InProgress) {
                throw PlanDayNotOpen::day($command->number, $day->status());
            }

            $card = $this->cards->findForUpdate($command->cardId);
            if ($card === null || ! $card->dayId()->equals($day->id())) {
                throw CardNotFound::withId($command->cardId);
            }

            $requeue = $card->answer($command->result, $command->attempts, $now);
            $this->cards->save($card);

            $retry = null;
            if ($requeue) {
                $lastPosition = 0;
                foreach ($this->cards->forDay($day->id()) as $other) {
                    if ($other->stage() === $card->stage()) {
                        $lastPosition = max($lastPosition, $other->position());
                    }
                }
                $retry = $card->retry(DayCardId::generate(), $lastPosition + 1);
                $this->cards->insertAll([$retry]);
            }

            return new AnswerOutcome($card, $retry);
        });
    }
}
