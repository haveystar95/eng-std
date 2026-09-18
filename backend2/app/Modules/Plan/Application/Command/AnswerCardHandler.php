<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\AnswerOutcome;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Exception\CardNotFound;
use App\Modules\Plan\Domain\Exception\CardResultNotAllowed;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\Service\ReturnDay;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Records one answer. A first lapse — a choice answered wrong, a phrase said aloud given up on after two attempts
 * (SESSION-1d, DECISIONS п. 327) — deals the same card again at the end of its stage: its options and tiles in another
 * order, seeded by the original card (наряд SESSION-1a, D-06), and a phrase card said with another filler of its frame
 * ({@see DayDealer::again()}); a second one marks the unit to return on the next day. The card is locked for the
 * transaction so a replayed answer is refused rather than counted twice.
 *
 * What the client may write is the kind's (D-31): a judged card's pass is the judge's, so the client only gives it
 * up; a walkthrough is walked or skipped; the voice never writes `failed`. Anything else is refused before the card is
 * touched — a pass nobody judged is not stored.
 *
 * The day's numbers are refolded here, from the day's own cards — the same calculator that closes
 * the day, over the same rows. They are a projection of the answer log and not a second tally
 * beside it: nothing is incremented, everything is counted again. That is what makes «39 из 75»
 * true while the day is still being walked, instead of appearing only at the close. The card's stage
 * is counted the same way, for the stage's summary.
 */
final readonly class AnswerCardHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private DayDealer $dealer,
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
            if (! $card->kind()->allows($command->result)) {
                throw CardResultNotAllowed::of($card->kind(), $command->result);
            }

            $requeue = $card->answer($command->result, $command->attempts, $command->response, $now, $command->choice);
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
                $retry = $card->retry(DayCardId::generate(), $lastPosition + 1, $this->dealer->again($plan, $card, $dealt));
                $this->cards->insertAll([$retry]);
                $dealt[] = $retry;
            }

            $metrics = $this->metrics->calculate($dealt);
            $this->plans->saveDayMetrics($day->id(), $metrics);
            $stage = array_values(array_filter($dealt, static fn (DayCard $c): bool => $c->stage() === $card->stage()));

            $dayNumbers = [];
            foreach ($plan->days() as $planDay) {
                $dayNumbers[$planDay->id()->value] = $planDay->number();
            }

            return new AnswerOutcome(
                card: $card,
                requeued: $retry,
                unitReturns: $card->returns(),
                returnsDay: $card->returns() ? ReturnDay::of($plan, $day) : null,
                metrics: $metrics,
                stageMinutes: $this->metrics->calculate($stage)->minutesSpent,
                targetLang: $plan->targetLang()->value,
                dayNumbers: $dayNumbers,
            );
        });
    }
}
