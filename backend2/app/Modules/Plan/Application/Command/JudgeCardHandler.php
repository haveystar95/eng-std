<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\JudgeOutcome;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\SlotJudge;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Exception\CardAlreadyAnswered;
use App\Modules\Plan\Domain\Exception\CardNotFound;
use App\Modules\Plan\Domain\Exception\CardNotJudged;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Judges one spoken attempt a card asks the judge for — `speak_answer` and the own-word round of «Скажи целиком»
 * (`phrase_other_slot`, наряд FIX-2 п. 5; `speak_retell` left the set with наряд BACK-TAILS-1 §1.1) — and writes the
 * ruling on the card.
 *
 * In three steps, because the ruling may take a model's eight seconds and a row lock must not: the card is found and
 * checked WITHOUT a lock (whose plan, which day, which kind, still open); the judge rules outside any transaction
 * (D-27); then the card is locked, checked again — a second attempt that raced this one may have answered it in
 * the meantime, and a replay is refused, never counted twice — and the attempt is written with the day's numbers
 * refolded from its cards, as every answer refolds them.
 *
 * `hinted` is the client's word that the frame was on screen before the attempt; «Скажи целиком» always shows its
 * frame, so there it means nothing and is not kept (D-16).
 */
final readonly class JudgeCardHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private PlanRepository $plans,
        private DayMetricsCalculator $metrics,
        private SlotJudge $judge,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(JudgeCard $command): JudgeOutcome
    {
        $now = $this->clock->now();
        $plan = $this->access->owned($command->planId, $command->actorId);
        $day = $plan->day($command->number);
        if ($day->status() !== DayStatus::InProgress) {
            throw PlanDayNotOpen::day($command->number, $day->status());
        }

        $card = $this->cardOf($day, $command->cardId, forUpdate: false);
        if (! $card->kind()->asksJudge()) {
            throw CardNotJudged::of($card->kind());
        }
        if ($card->isAnswered()) {
            throw CardAlreadyAnswered::withId($card->id());
        }

        $hinted = $command->hinted && $card->kind() !== CardKind::PhraseOtherSlot;
        $verdict = $this->judge->judge($plan, $card, $command->heard, $now);

        $judged = $this->tx->run(function () use ($command, $day, $verdict, $hinted, $now): DayCard {
            $card = $this->cardOf($day, $command->cardId, forUpdate: true);
            $card->judge($verdict->accepted, $hinted, $verdict->response($command->heard, $hinted), $now);
            $this->cards->save($card);
            $this->plans->saveDayMetrics($day->id(), $this->metrics->calculate($this->cards->forDay($day->id())));

            return $card;
        });

        $dayNumbers = [];
        foreach ($plan->days() as $planDay) {
            $dayNumbers[$planDay->id()->value] = $planDay->number();
        }

        return new JudgeOutcome($judged, $verdict, $plan->targetLang()->value, $dayNumbers, $command->heard);
    }

    private function cardOf(PlanDay $day, DayCardId $id, bool $forUpdate): DayCard
    {
        $card = $forUpdate ? $this->cards->findForUpdate($id) : $this->cards->find($id);
        if ($card === null || ! $card->dayId()->equals($day->id())) {
            throw CardNotFound::withId($id);
        }

        return $card;
    }
}
