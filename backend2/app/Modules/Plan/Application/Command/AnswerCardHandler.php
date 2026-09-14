<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\AnswerOutcome;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Exception\CardNotFound;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Records one answer. A first failure deals the same card again at the end of its stage; a
 * second one marks the unit to return on the next content day. The card is locked for the
 * transaction so a replayed answer is refused rather than counted twice.
 *
 * The day's numbers are refolded here, from the day's own cards — the same calculator that closes
 * the day, over the same rows. They are a projection of the answer log and not a second tally
 * beside it: nothing is incremented, everything is counted again. That is what makes «39 из 75»
 * true while the day is still being walked, instead of appearing only at the close.
 */
final readonly class AnswerCardHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private PlanRepository $plans,
        private DayMetricsCalculator $metrics,
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

            $dealt = $this->cards->forDay($day->id());

            $retry = null;
            if ($requeue) {
                $lastPosition = 0;
                foreach ($dealt as $other) {
                    if ($other->stage() === $card->stage()) {
                        $lastPosition = max($lastPosition, $other->position());
                    }
                }
                $retry = $card->retry(DayCardId::generate(), $lastPosition + 1);
                $this->cards->insertAll([$retry]);
                $dealt[] = $retry;
            }

            $this->plans->saveDayMetrics($day->id(), $this->metrics->calculate($dealt));

            return new AnswerOutcome($card, $retry);
        });
    }
}
